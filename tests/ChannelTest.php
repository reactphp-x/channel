<?php

namespace ReactphpX\Channel\Tests;

use PHPUnit\Framework\TestCase;
use ReactphpX\Channel\Channel;
use ReactphpX\Channel\ChannelClosedException;
use React\EventLoop\Loop;
use function React\Async\async;
use function React\Async\await;
use function React\Async\delay as sleep;

class ChannelTest extends TestCase
{
    public function testBasicPushPop()
    {
        $channel = new Channel(1);
        $loop = Loop::get();

        $result = await($channel->push('test'));
        $this->assertTrue($result);

        $data = await($channel->pop());
        $this->assertEquals('test', $data);
    }

    public function testAsyncPushPop()
    {
        $channel = new Channel(1);
        $loop = Loop::get();

        $results = [];
        $promises = [];

        // 创建多个生产者
        for ($i = 0; $i < 3; $i++) {
            $promises[] = async(function() use ($channel, $i, &$results) {
                sleep(0.1 * $i);
                $result = await($channel->push("data$i"));
                $results[] = "push$i:" . ($result ? 'success' : 'failed');
            })();
        }

        // 创建多个消费者
        for ($i = 0; $i < 3; $i++) {
            $promises[] = async(function() use ($channel, $i, &$results) {
                sleep(0.2 * $i);
                $data = await($channel->pop());
                $results[] = "pop$i:" . ($data ?? 'null');
            })();
        }

        // 等待所有操作完成
        await(\React\Promise\all($promises));

        $this->assertCount(6, $results);
        $this->assertContains('push0:success', $results);
        $this->assertContains('pop0:data0', $results);
    }

    public function testChannelStats()
    {
        $channel = new Channel(2);
        $loop = Loop::get();

        $stats = $channel->stats();
        $this->assertEquals(0, $stats['consumer_num']);
        $this->assertEquals(0, $stats['producer_num']);
        $this->assertEquals(0, $stats['queue_num']);
        $this->assertEquals(2, $stats['capacity']);
        $this->assertFalse($stats['closed']);

        // 填充通道
        await($channel->push('data1'));
        await($channel->push('data2'));

        $stats = $channel->stats();
        $this->assertEquals(0, $stats['consumer_num']);
        $this->assertEquals(0, $stats['producer_num']);
        $this->assertEquals(2, $stats['queue_num']);

        // 尝试推送更多数据（应该等待）
        $promise = async(function() use ($channel) {
            return await($channel->push('data3'));
        })();

        $stats = $channel->stats();
        $this->assertEquals(1, $stats['producer_num']);

        // 消费一个数据
        await($channel->pop());

        $stats = $channel->stats();

        $this->assertEquals(0, $stats['producer_num']);
        $this->assertEquals(2, $stats['queue_num']);
    }

    public function testChannelClose()
    {
        $channel = new Channel(1);
        $loop = Loop::get();

        $exception = null;
        $channel->push('data1');
        $channel->close();

        try {
            await($channel->push('data2'));
        } catch (ChannelClosedException $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(ChannelClosedException::class, $exception);

        // 检查关闭后的状态
        $stats = $channel->stats();
        $this->assertTrue($stats['closed']);
    }

    public function testTimeout()
    {
        $channel = new Channel(1);
        $loop = Loop::get();

        // 填充通道
        $channel->push('data1');

        $exception = null;
        try {
            $result = await($channel->push('data2', 0.1));
            $this->assertFalse($result);
        } catch (\React\Promise\Timer\TimeoutException $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(\React\Promise\Timer\TimeoutException::class, $exception);

        $data = await($channel->pop(0.1));
        $this->assertEquals('data1', $data);
    }

    public function testChannelPushTimeout()
    {
        $channel = new Channel(1);
        $loop = Loop::get();

        $exception = null;
        try {
            $result = await($channel->push('data1'));
            $result = await($channel->push('data2', 0.1));
        } catch (\React\Promise\Timer\TimeoutException $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(\React\Promise\Timer\TimeoutException::class, $exception);
    }

    public function testChannelPopTimeout()
    {
        $channel = new Channel(1);
        $loop = Loop::get();

        // $channel->push('data1');
        $exception = null;
        try {
            $data = await($channel->pop(0.1));
        } catch (\React\Promise\Timer\TimeoutException $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(\React\Promise\Timer\TimeoutException::class, $exception);
    }
} 