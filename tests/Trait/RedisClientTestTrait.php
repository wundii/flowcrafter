<?php

declare(strict_types=1);

namespace Tests\Trait;

use Redis;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Container\StartedGenericContainer;
use Testcontainers\Wait\WaitForHostPort;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\Config\RedisConfig;
use Wundii\Flowcrafter\Storage\Redis as RedisStorage;

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

        $redis = new RedisStorage(
            new RedisConfig(
                self::$container->getHost(),
                self::$container->getMappedPort(self::PORT),
            ),
            ':memory:',
        );
        $redis->initializeDatabase();
        $redis->truncateFlowList();

        return $redis;
    }
}
