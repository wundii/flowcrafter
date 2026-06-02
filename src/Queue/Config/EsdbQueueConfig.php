<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Queue\Config;

use Wundii\Flowcrafter\Interface\QueueConfigInterface;
use Wundii\Flowcrafter\Queue\EsdbQueue;

final readonly class EsdbQueueConfig implements QueueConfigInterface
{
    public function __construct(
        private string $url,
        private string $apiToken,
    ) {
    }

    public function getQueueClass(): string
    {
        return EsdbQueue::class;
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
