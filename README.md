# ReactPHP Channel

一个基于 ReactPHP 的通道（Channel）实现，提供了类似 Go 语言的通道功能，支持异步通信、超时控制和等待组等功能。

## 特性

- 异步通道通信
- 支持带缓冲的通道
- 超时控制
- 通道关闭机制
- 等待组（WaitGroup）支持
- 完全基于 Promise 的异步操作
- 支持类似 Swoole 协程风格的编程

## 安装

```bash
composer require reactphp-x/channel -vvv
```

## 使用示例

### Channel 基本使用

```php
use ReactphpX\Channel\Channel;
use React\EventLoop\Loop;

$loop = Loop::get();
$channel = new Channel(1); // 创建容量为1的通道

// 生产者
$channel->push('data')->then(
    function() {
        echo "Data pushed successfully\n";
    },
    function($error) {
        echo "Push failed: " . $error->getMessage() . "\n";
    }
);

// 消费者
$channel->pop()->then(
    function($data) {
        echo "Received: " . $data . "\n";
    },
    function($error) {
        echo "Pop failed: " . $error->getMessage() . "\n";
    }
);

$loop->run();
```

### 协程风格使用

```php
use ReactphpX\Channel\Channel;
use ReactphpX\Channel\WaitGroup;
use React\EventLoop\Loop;
use function React\Async\async;
use function React\Async\await;
use function React\Async\delay as sleep;

$loop = Loop::get();

// 类似 Swoole 协程风格的 Channel 使用
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

// 类似 Swoole 协程风格的 WaitGroup 使用
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
```

### 带超时的操作

```php
// 带超时的推送
$channel->push('data', 1.0)->then(
    function() {
        echo "Push succeeded\n";
    },
    function($error) {
        echo "Push failed: " . $error->getMessage() . "\n";
    }
);

// 带超时的弹出
$channel->pop(1.0)->then(
    function($data) {
        echo "Pop succeeded, data: " . ($data ?? "null") . "\n";
    },
    function($error) {
        echo "Pop failed: " . $error->getMessage() . "\n";
    }
);
```

### WaitGroup 使用

```php
use ReactphpX\Channel\WaitGroup;

$wg = new WaitGroup();
$wg->add(2); // 添加两个任务

// 模拟异步任务
Loop::addTimer(0.5, function() use ($wg) {
    echo "Task 1 completed\n";
    $wg->done();
});

Loop::addTimer(1.0, function() use ($wg) {
    echo "Task 2 completed\n";
    $wg->done();
});

// 等待所有任务完成
$wg->wait()->then(function() {
    echo "All tasks completed\n";
});
```

### 带超时的 WaitGroup

```php
$wg = new WaitGroup();
$wg->add(1);

// 模拟长时间运行的任务
Loop::addTimer(2.0, function() use ($wg) {
    echo "Long running task completed\n";
    $wg->done();
});

// 等待最多1秒
$wg->wait(1.0)->then(
    function() {
        echo "Wait completed within timeout\n";
    },
    function($error) {
        echo "Wait timed out: " . $error->getMessage() . "\n";
    }
);
```

## API 文档

### Channel

- `__construct(int $capacity = 1)`: 创建一个容量为 `$capacity` 的通道
- `push($data, float $timeout = -1)`: 推送数据到通道，可设置超时
- `pop(float $timeout = -1)`: 从通道弹出数据，可设置超时
- `close()`: 关闭通道
- `isClosed()`: 检查通道是否已关闭
- `length()`: 获取通道当前长度
- `isEmpty()`: 检查通道是否为空
- `isFull()`: 检查通道是否已满

### WaitGroup

- `__construct()`: 创建一个新的等待组
- `add(int $delta = 1)`: 添加任务计数
- `done()`: 标记一个任务完成
- `wait(float $timeout = -1)`: 等待所有任务完成，可设置超时
- `count()`: 获取当前任务计数

## 示例

更多示例请查看 `examples` 目录：

- `channel_examples.php`: Channel 的基本使用示例
- `waitgroup_examples.php`: WaitGroup 的基本使用示例
- `coroutine_style_examples.php`: 协程风格的使用示例

## 许可证

MIT 