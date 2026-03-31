<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Config;

use Wundii\Flowcrafter\Interface\StorageConfigInterface;
use Wundii\Flowcrafter\Storage\Redis;

final readonly class RedisConfig implements StorageConfigInterface
{
    public function __construct(
        private string $host,
        private int $port,
    ) {
    }

    public function getStorageClass(): string
    {
        return Redis::class;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
