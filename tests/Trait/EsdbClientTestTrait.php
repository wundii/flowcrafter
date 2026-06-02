<?php

declare(strict_types=1);

namespace Tests\Trait;

use Thenativeweb\Eventsourcingdb\Client;
use Thenativeweb\Eventsourcingdb\Container;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Queue\Config\EsdbQueueConfig;
use Wundii\Flowcrafter\Queue\EsdbQueue;
use Wundii\Flowcrafter\Storage\Config\EsdbStorageConfig;
use Wundii\Flowcrafter\Storage\EsdbStorage;

trait EsdbClientTestTrait
{
    private Container $container;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->startContainer();
        $this->client = $this->container->getClient();
    }

    protected function tearDown(): void
    {
        $this->container->stop();
        parent::tearDown();
    }

    protected function startContainer(): Container
    {
        $container = (new Container())->withImageTag('latest');
        $container->start();

        return $container;
    }

    protected function storage(): StorageInterface
    {
        $esdbStorage = new EsdbStorage(
            new EsdbStorageConfig(
                $this->container->getBaseUrl(),
                $this->container->getApiToken(),
            ),
            ':memory:',
        );
        $esdbStorage->initializeDatabase();
        $esdbStorage->truncateFlowList();

        return $esdbStorage;
    }

    protected function queue(): QueueInterface
    {
        $esdbQueue = new EsdbQueue(
            new EsdbQueueConfig(
                $this->container->getBaseUrl(),
                $this->container->getApiToken(),
            ),
        );
        $esdbQueue->initializeQueue();

        return $esdbQueue;
    }
}
