<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ReactphpX\Channel\Channel;
use React\EventLoop\Loop;

$loop = Loop::get();

// 示例1: 基本关闭操作
$channel1 = new Channel(2);
$channel1->push('data1');
$channel1->push('data2');
$channel1->close();

$channel1->pop()->then(
    function ($data) {
        echo "Received from closed channel: " . $data . "\n";
    },
    function ($error) {
        echo "Error from closed channel: " . $error->getMessage() . "\n";
    }
);

// 示例2: 超时操作
$channel2 = new Channel(1);
$channel2->push('data1');

// 尝试在已满的通道上推送数据，设置1秒超时
$channel2->push('data2', 1.0)->then(
    function ($result) {
        echo "Push succeeded: " . ($result ? "true" : "false") . "\n";
    },
    function ($error) {
        echo "Push failed: " . $error->getMessage() . "\n";
    }
);

// 示例3: 从空通道读取，设置超时
$channel3 = new Channel(1);
$channel3->pop(1.0)->then(
    function ($data) {
        echo "Pop succeeded, data: " . ($data ?? "null") . "\n";
    },
    function ($error) {
        echo "Pop failed: " . $error->getMessage() . "\n";
    }
);

// 示例4: 关闭通道时处理等待的消费者和生产者
$channel4 = new Channel(1);
$channel4->push('data1');

// 设置一个等待的消费者
$channel4->pop()->then(
    function ($data) {
        echo "Consumer received: " . $data . "\n";
    },
    function ($error) {
        echo "Consumer error: " . $error->getMessage() . "\n";
    }
);

$channel4->push('data1');


// 设置一个等待的生产者
$channel4->push('data2')->then(
    function ($result) {
        echo "Producer succeeded: " . ($result ? "true" : "false") . "\n";
    },
    function ($error) {
        echo "Producer error: " . $error->getMessage() . "\n";
    }
);

// 关闭通道，所有等待的操作都会收到错误
$channel4->close();

$loop->run(); 