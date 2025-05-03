<?php

namespace ReactphpX\Channel;

use React\Promise\PromiseInterface;

class WaitGroup
{
    private Channel $channel;
    private int $count = 0;

    public function __construct()
    {
        $this->channel = new Channel(1);
    }

    public function add(int $delta = 1): void
    {
        $this->count += $delta;
        if ($this->count < 0) {
            throw new \RuntimeException('WaitGroup counter cannot be negative');
        }
    }

    public function done(): void
    {
        $this->add(-1);
        if ($this->count === 0) {
            $this->channel->push(true);
        }
    }

    public function wait(float $timeout = -1): PromiseInterface
    {
        if ($this->count === 0) {
            return \React\Promise\resolve(true);
        }

        return $this->channel->pop($timeout);
    }

    public function count(): int
    {
        return $this->count;
    }
} 