<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Config;

use Wundii\Flowcrafter\Interface\StorageConfigInterface;
use Wundii\Flowcrafter\Storage\MySql;

final readonly class MySqlConfig implements StorageConfigInterface
{
    public function __construct(
        private string $host,
        private int $port,
        private string $database,
        private string $username,
        private string $password = '',
    ) {
    }

    public function getStorageClass(): string
    {
        return MySql::class;
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
