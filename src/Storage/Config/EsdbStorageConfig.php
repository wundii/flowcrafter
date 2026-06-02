<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Config;

use Wundii\Flowcrafter\Interface\StorageConfigInterface;
use Wundii\Flowcrafter\Storage\EsdbStorage;

final readonly class EsdbStorageConfig implements StorageConfigInterface
{
    public function __construct(
        private string $url,
        private string $apiToken,
    ) {
    }

    public function getStorageClass(): string
    {
        return EsdbStorage::class;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }
}
