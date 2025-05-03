<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ReactphpX\Channel\Channel;
use ReactphpX\Channel\WaitGroup;
use React\EventLoop\Loop;
use function React\Async\async;
use function React\Async\await;
use function React\Async\delay as sleep;

$loop = Loop::get();

// 示例1: 类似 Swoole 协程风格的 Channel 使用
async(function() {
    $channel = new Channel(1);
    
    // 生产者协程
    async(function() use ($channel) {
        for($i = 0; $i < 10; $i++) {
            sleep(1.0);
            await($channel->push(['rand' => rand(1000, 9999), 'index' => $i]));
            echo "{$i}\n";
        }
    })();

    // 消费者协程
    async(function() use ($channel) {
        while(true) {
            try {
                $data = await($channel->pop(2.0));
                var_dump($data);
            } catch (\Exception $e) {
                if ($e instanceof \ReactphpX\Channel\ChannelClosedException) {
                    echo "Channel closed\n";
                    break;
                }
                if ($e instanceof \React\Promise\Timer\TimeoutException) {
                    echo "Timeout\n";
                    break;
                }
                throw $e;
            }
        }
    })();
})();

// 示例2: 类似 Swoole 协程风格的 WaitGroup 使用
async(function() {
    $wg = new WaitGroup();
    $result = [];

    // 第一个协程任务
    $wg->add();
    async(function() use ($wg, &$result) {
        try {
            $client = new \React\Http\Browser();
            $response = await($client->get('https://www.taobao.com'));
            $result['taobao'] = (string) $response->getBody();
        } catch (\Exception $e) {
            $result['taobao'] = "Error: " . $e->getMessage();
        }
        $wg->done();
    })();

    // 第二个协程任务
    $wg->add();
    async(function() use ($wg, &$result) {
        try {
            $client = new \React\Http\Browser();
            $response = await($client->get('https://www.baidu.com'));
            $result['baidu'] = (string) $response->getBody();
        } catch (\Exception $e) {
            $result['baidu'] = "Error: " . $e->getMessage();
        }
        $wg->done();
    })();

    // 等待所有任务完成
    await($wg->wait());
    var_dump($result);
})();

$loop->run(); 