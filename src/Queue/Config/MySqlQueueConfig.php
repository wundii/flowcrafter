<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Queue\Config;

use Wundii\Flowcrafter\Interface\QueueConfigInterface;
use Wundii\Flowcrafter\Queue\MySqlQueue;

final readonly class MySqlQueueConfig implements QueueConfigInterface
{
    public function __construct(
        private string $host,
        private int $port,
        private string $database,
        private string $username,
        private string $password = '',
    ) {
    }

    public function getQueueClass(): string
    {
        return MySqlQueue::class;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
