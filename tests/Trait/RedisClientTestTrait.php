<?php

declare(strict_types=1);

namespace Tests\Trait;

use Redis;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Container\StartedGenericContainer;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\Config\RedisConfig;
use Wundii\Flowcrafter\Storage\Redis as RedisStorage;

trait RedisClientTestTrait
{
    private const PORT = 6379;

    private StartedGenericContainer $container;

    private Redis $client;

    protected function setUp(): void
    {
        parent::setUp();

        $port = self::PORT;

        $this->container = $this->startContainer($port);
        $host = $this->container->getHost();
        $port = $this->container->getMappedPort($port);

        $this->client = new Redis();
        $this->client->connect($host, $port);
    }

    protected function tearDown(): void
    {
        $this->container->stop();
        parent::tearDown();
    }

    protected function storage(): StorageInterface
    {
        $redis = new RedisStorage(
            new RedisConfig(
                $this->container->getHost(),
                $this->container->getMappedPort(6379),
            ),
        );
        $redis->initializeDatabase();

        return $redis;
    }

    protected function startContainer(int $port): StartedGenericContainer
    {
        return (new GenericContainer('redis:latest'))
            ->withExposedPorts($port)
            ->start();
    }
}
