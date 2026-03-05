<?php

declare(strict_types=1);

namespace Tests\Trait;

use PDO as Client;
use Testcontainers\Container\StartedGenericContainer;
use Testcontainers\Modules\MySQLContainer;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\MySql;

trait MySqlClientTestTrait
{
    private const DATABASE = 'flowCrafter';

    private const PORT = 3306;

    private const USERNAME = 'weyland';

    private const PASSWORD = 'yutani';

    private StartedGenericContainer $container;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $port = self::PORT;
        $database = self::DATABASE;
        $username = self::USERNAME;
        $password = self::PASSWORD;

        $this->container = $this->startContainer($port, $database, $username, $password);
        $host = $this->container->getHost();
        $port = $this->container->getMappedPort($port);

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $this->client = new Client($dsn, $username, $password, [
            Client::ATTR_ERRMODE => Client::ERRMODE_EXCEPTION,
            Client::ATTR_DEFAULT_FETCH_MODE => Client::FETCH_ASSOC,
            Client::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    protected function tearDown(): void
    {
        $this->container->stop();
        parent::tearDown();
    }

    public function storage(): StorageInterface
    {
        $mySql = new MySql(
            $this->container->getHost(),
            $this->container->getMappedPort(self::PORT),
            self::DATABASE,
            self::USERNAME,
            self::PASSWORD
        );
        $mySql->initializeDatabase();

        return $mySql;
    }

    protected function startContainer(int $port, string $database, string $username, string $password): StartedGenericContainer
    {
        return (new MySQLContainer())
            ->withMySQLDatabase($database)
            ->withMySQLUser($username, $password)
            ->withExposedPorts($port)
            ->start();
    }
}
