<?php

declare(strict_types=1);

namespace Tests\Trait;

use Redis;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Container\StartedGenericContainer;
use Testcontainers\Wait\WaitForHostPort;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Queue\Config\RedisQueueConfig;
use Wundii\Flowcrafter\Queue\RedisQueue;
use Wundii\Flowcrafter\Storage\Config\RedisStorageConfig;
use Wundii\Flowcrafter\Storage\RedisStorage;

trait RedisClientTestTrait
{
    private const PORT = 6379;

    private static ?StartedGenericContainer $container = null;

    private Redis $client;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$container = (new GenericContainer('redis:latest'))
            ->withExposedPorts(self::PORT)
            ->withWait(new WaitForHostPort(self::PORT))
            ->start();

        usleep(100000);
    }

    public static function tearDownAfterClass(): void
    {
        self::$container?->stop();
        self::$container = null;

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$container instanceof StartedGenericContainer) {
            self::fail('Redis container was not started');
        }

        $this->client = new Redis();
        $this->client->connect(self::$container->getHost(), self::$container->getMappedPort(self::PORT));
        $this->client->flushDB();
    }

    protected function storage(): StorageInterface
    {
        if (!self::$container instanceof StartedGenericContainer) {
            self::fail('Redis container was not started');
        }

        $redisStorage = new RedisStorage(
            new RedisStorageConfig(
                self::$container->getHost(),
                self::$container->getMappedPort(self::PORT),
            ),
            ':memory:',
        );
        $redisStorage->initializeDatabase();
        $redisStorage->truncateFlowList();

        return $redisStorage;
    }

    protected function queue(): QueueInterface
    {
        if (!self::$container instanceof StartedGenericContainer) {
            self::fail('Redis container was not started');
        }

        $redisQueue = new RedisQueue(
            new RedisQueueConfig(
                self::$container->getHost(),
                self::$container->getMappedPort(self::PORT),
            ),
        );
        $redisQueue->initializeQueue();

        return $redisQueue;
    }
}
