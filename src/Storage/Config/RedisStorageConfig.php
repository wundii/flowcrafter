<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Config;

use Wundii\Flowcrafter\Interface\StorageConfigInterface;
use Wundii\Flowcrafter\Storage\RedisStorage;

final readonly class RedisStorageConfig implements StorageConfigInterface
{
    public function __construct(
        private string $host,
        private int $port,
        private ?string $password = null,
    ) {
    }

    public function getStorageClass(): string
    {
        return RedisStorage::class;
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
