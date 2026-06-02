<?php

declare(strict_types=1);

namespace Tests\Trait;

use PDO as Client;
use Testcontainers\Container\StartedGenericContainer;
use Testcontainers\Modules\MariaDBContainer;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Queue\Config\MySqlQueueConfig;
use Wundii\Flowcrafter\Queue\MySqlQueue;
use Wundii\Flowcrafter\Storage\Config\MySqlStorageConfig;
use Wundii\Flowcrafter\Storage\MySqlStorage;

trait MySqlClientTestTrait
{
    private const DATABASE = 'flowCrafter';

    private const PORT = 3306;

    private const USERNAME = 'weyland';

    private const PASSWORD = 'yutani';

    private static ?StartedGenericContainer $container = null;

    private Client $client;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$container = (new MariaDBContainer())
            ->withMariaDBDatabase(self::DATABASE)
            ->withMariaDBUser(self::USERNAME, self::PASSWORD)
            ->withExposedPorts(self::PORT)
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
            self::fail('MariaDB container was not started');
        }

        $host = self::$container->getHost();
        $port = self::$container->getMappedPort(self::PORT);
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, self::DATABASE);
        $this->client = new Client($dsn, self::USERNAME, self::PASSWORD, [
            Client::ATTR_ERRMODE => Client::ERRMODE_EXCEPTION,
            Client::ATTR_DEFAULT_FETCH_MODE => Client::FETCH_ASSOC,
        ]);

        $this->truncateAllTables();
    }

    protected function storage(): StorageInterface
    {
        if (!self::$container instanceof StartedGenericContainer) {
            self::fail('MariaDB container was not started');
        }

        $mySqlStorage = new MySqlStorage(
            new MySqlStorageConfig(
                self::$container->getHost(),
                self::$container->getMappedPort(self::PORT),
                self::DATABASE,
                self::USERNAME,
                self::PASSWORD,
            ),
            ':memory:',
        );
        $mySqlStorage->initializeDatabase();
        $mySqlStorage->truncateFlowList();

        return $mySqlStorage;
    }

    protected function queue(): QueueInterface
    {
        if (!self::$container instanceof StartedGenericContainer) {
            self::fail('MariaDB container was not started');
        }

        $mySqlQueue = new MySqlQueue(
            new MySqlQueueConfig(
                self::$container->getHost(),
                self::$container->getMappedPort(self::PORT),
                self::DATABASE,
                self::USERNAME,
                self::PASSWORD,
            ),
        );
        $mySqlQueue->initializeQueue();

        return $mySqlQueue;
    }

    private function truncateAllTables(): void
    {
        /** @var list<string> $tables */
        $tables = $this->client->query('SHOW TABLES')->fetchAll(Client::FETCH_COLUMN);
        if ($tables === []) {
            return;
        }

        $this->client->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $this->client->exec(sprintf('TRUNCATE TABLE `%s`', $table));
        }

        $this->client->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
