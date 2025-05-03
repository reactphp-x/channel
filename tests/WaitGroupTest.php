<?php

namespace ReactphpX\Channel\Tests;

use PHPUnit\Framework\TestCase;
use ReactphpX\Channel\WaitGroup;
use React\EventLoop\Loop;
use function React\Async\async;
use function React\Async\await;
use function React\Async\delay as sleep;

class WaitGroupTest extends TestCase
{
    public function testBasicWait()
    {
        $wg = new WaitGroup();
        $loop = Loop::get();

        $wg->add();

        async(function() use ($wg) {
            sleep(0.1);
            $wg->done();
        })();

        $result = await($wg->wait());
        $this->assertTrue($result);
    }

    public function testMultipleTasks()
    {
        $wg = new WaitGroup();
        $loop = Loop::get();

        $results = [];
        $promises = [];

        // 添加多个任务
        for ($i = 0; $i < 5; $i++) {
            $wg->add();
            $promises[] = async(function() use ($wg, $i, &$results) {
                sleep(0.1 * $i);
                $results[] = "task$i";
                $wg->done();
            })();
        }

        $result = await($wg->wait());
        $this->assertTrue($result);
        $this->assertCount(5, $results);
        $this->assertEquals(0, $wg->count());
    }

    public function testTimeout()
    {
        $wg = new WaitGroup();
        $loop = Loop::get();

        $wg->add();

        async(function() use ($wg) {
            sleep(0.2); // 比超时时间长
            $wg->done();
        })();
        try {
            $result = await($wg->wait(0.1));
            $this->assertFalse($result);
        } catch (\React\Promise\Timer\TimeoutException $e) {
            $this->assertTrue(true);
        }
    }

    public function testNegativeCounter()
    {
        $wg = new WaitGroup();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WaitGroup counter cannot be negative');
        $wg->add(-1);
    }

    public function testImmediateWait()
    {
        $wg = new WaitGroup();
        $loop = Loop::get();

        $result = await($wg->wait());
        $this->assertTrue($result);
    }
} 