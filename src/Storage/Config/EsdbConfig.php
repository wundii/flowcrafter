<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Config;

use Wundii\Flowcrafter\Interface\StorageConfigInterface;

final readonly class EsdbConfig implements StorageConfigInterface
{
    public function __construct(
        private string $url,
        private string $apiToken,
    ) {
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
