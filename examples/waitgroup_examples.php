<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ReactphpX\Channel\WaitGroup;
use React\EventLoop\Loop;

$loop = Loop::get();

// 示例1: 基本 WaitGroup 使用
$wg1 = new WaitGroup();
$wg1->add(2); // 添加两个任务

// 模拟两个异步任务
Loop::addTimer(0.5, function() use ($wg1) {
    echo "Task 1 completed\n";
    $wg1->done();
});

Loop::addTimer(1.0, function() use ($wg1) {
    echo "Task 2 completed\n";
    $wg1->done();
});

$wg1->wait()->then(function() {
    echo "All tasks completed\n";
});

// 示例2: 带超时的 WaitGroup
$wg2 = new WaitGroup();
$wg2->add(1);

// 模拟一个长时间运行的任务
Loop::addTimer(2.0, function() use ($wg2) {
    echo "Long running task completed\n";
    $wg2->done();
});

// 等待最多1秒
$wg2->wait(1.0)->then(
    function() {
        echo "Wait completed within timeout\n";
    },
    function($error) {
        echo "Wait timed out: " . $error->getMessage() . "\n";
    }
);

// 示例3: 动态添加任务
$wg3 = new WaitGroup();
$wg3->add(1);

// 第一个任务完成后添加新任务
Loop::addTimer(0.5, function() use ($wg3) {
    echo "First task completed\n";
    $wg3->done();
    
    // 添加新任务
    $wg3->add(1);
    Loop::addTimer(0.5, function() use ($wg3) {
        echo "Second task completed\n";
        $wg3->done();
    });
});

$wg3->wait()->then(function() {
    echo "All dynamic tasks completed\n";
});

// 示例4: 错误处理
$wg4 = new WaitGroup();
$wg4->add(1);

// 模拟一个失败的任务
Loop::addTimer(0.5, function() use ($wg4) {
    echo "Task failed\n";
    $wg4->done();
});

$wg4->wait()->then(
    function() {
        echo "Wait completed successfully\n";
    },
    function($error) {
        echo "Wait failed: " . $error->getMessage() . "\n";
    }
);

$loop->run(); 