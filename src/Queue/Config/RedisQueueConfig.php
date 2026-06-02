<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Queue\Config;

use Wundii\Flowcrafter\Interface\QueueConfigInterface;
use Wundii\Flowcrafter\Queue\RedisQueue;

final readonly class RedisQueueConfig implements QueueConfigInterface
{
    public function __construct(
        private string $host,
        private int $port,
        private ?string $password = null,
    ) {
    }

    public function getQueueClass(): string
    {
        return RedisQueue::class;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
