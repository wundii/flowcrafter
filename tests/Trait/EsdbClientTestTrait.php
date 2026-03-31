<?php

declare(strict_types=1);

namespace Tests\Trait;

use Thenativeweb\Eventsourcingdb\Client;
use Thenativeweb\Eventsourcingdb\Container;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\Config\EsdbConfig;
use Wundii\Flowcrafter\Storage\Esdb;

trait EsdbClientTestTrait
{
    private const SQLiteFile = '/tmp/flowcrafter.sqlite';

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
        @unlink(self::SQLiteFile);
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
        $esdb = new Esdb(
            new EsdbConfig(
                $this->container->getBaseUrl(),
                $this->container->getApiToken(),
            ),
            self::SQLiteFile,
        );
        $esdb->initializeDatabase();

        return $esdb;
    }
}
