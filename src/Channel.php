<?php

namespace ReactphpX\Channel;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\Timer\timeout;
use SplQueue;

class ChannelClosedException extends \RuntimeException
{
    public function __construct(string $message = "Channel is closed", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class Channel
{
    private SplQueue $queue;
    private int $capacity;
    private bool $closed = false;
    private array $pushWaiters = [];
    private array $popWaiters = [];

    public function __construct(int $capacity = 1)
    {
        $this->queue = new SplQueue();
        $this->capacity = $capacity;
    }

    public function stats(): array
    {
        return [
            'consumer_num' => $this->getPopWaitersCount(),
            'producer_num' => $this->getPushWaitersCount(),
            'queue_num' => $this->queue->count(),
            'capacity' => $this->capacity,
            'closed' => $this->closed
        ];
    }

    public function push($data, float $timeout = -1)
    {
        if ($this->closed) {
            return \React\Promise\reject(new ChannelClosedException());
        }

        if ($this->queue->count() < $this->capacity) {
            $this->queue->enqueue($data);
            $this->wakeupPopWaiters();
            return \React\Promise\resolve(true);
        }

        if ($timeout === 0) {
            return \React\Promise\resolve(false);
        }

        $deferred = new Deferred();
        $this->pushWaiters[] = $deferred;

        $promise = $deferred->promise();
        
        if ($timeout > 0) {
            $promise = timeout($promise, $timeout)->then(null, function($e) use ($deferred){
                $key = array_search($deferred, $this->pushWaiters);
                if ($key !== false) {
                    unset($this->pushWaiters[$key]);
                }
                throw $e;
            });
        }

        return $promise->then(function ($result) use ($data) {
            $this->queue->enqueue($data);
            $this->wakeupPopWaiters();
            return $result;
        });
    }

    public function pop(float $timeout = -1): PromiseInterface
    {
        if ($this->closed && $this->queue->isEmpty()) {
            return \React\Promise\reject(new ChannelClosedException('Channel is closed and empty'));
        }

        if (!$this->queue->isEmpty()) {
            $data = $this->queue->dequeue();
            $this->wakeupPushWaiters();
            return \React\Promise\resolve($data);
        }

        if ($timeout === 0) {
            return \React\Promise\resolve(null);
        }

        $deferred = new Deferred();
        $this->popWaiters[] = $deferred;

        $promise = $deferred->promise();
        
        if ($timeout > 0) {
            $promise = timeout($promise, $timeout)->then(null, function($e) use ($deferred){
                $key = array_search($deferred, $this->popWaiters);
                if ($key !== false) {
                    unset($this->popWaiters[$key]);
                }
                throw $e;
            });
        }

        return $promise;
    }

    public function close(): void
    {
        $this->closed = true;
        $this->wakeupAllWaiters();
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function length(): int
    {
        return $this->queue->count();
    }

    public function isEmpty(): bool
    {
        return $this->queue->isEmpty();
    }

    public function isFull(): bool
    {
        return $this->queue->count() >= $this->capacity;
    }

    private function wakeupPushWaiters(): void
    {
        while (!empty($this->pushWaiters) && $this->queue->count() < $this->capacity) {
            $deferred = array_shift($this->pushWaiters);
            $deferred->resolve(true);
        }
    }

    private function wakeupPopWaiters(): void
    {
        while (!empty($this->popWaiters) && !$this->queue->isEmpty()) {
            $deferred = array_shift($this->popWaiters);
            $deferred->resolve($this->queue->dequeue());
        }
    }

    private function wakeupAllWaiters(): void
    {
        foreach ($this->pushWaiters as $deferred) {
            $deferred->reject(new ChannelClosedException());
        }
        $this->pushWaiters = [];

        foreach ($this->popWaiters as $deferred) {
            $deferred->reject(new ChannelClosedException());
        }
        $this->popWaiters = [];
    }

    public function getPushWaitersCount(): int
    {
        return count($this->pushWaiters);
    }

    public function getPopWaitersCount(): int
    {
        return count($this->popWaiters);
    }
}

