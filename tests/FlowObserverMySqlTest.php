<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\MySqlClientTestTrait;
use Wundii\Flowcrafter\FlowObserver;
use Wundii\Flowcrafter\Storage\MySql;

final class FlowObserverMySqlTest extends TestCase
{
    use MySqlClientTestTrait;

    public function testRunObserverWithoutMessages(): void
    {
        $mySql = new MySql(
            $this->container->getHost(),
            $this->container->getMappedPort(self::PORT),
            self::DATABASE,
            self::USERNAME,
            self::PASSWORD
        );

        $flowObserver = new FlowObserver($mySql);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_QUEUE);
        $this->assertCount(0, iterator_to_array($stmt->fetchAll()));
    }

    public function testRunObserverWithMessages(): void
    {
        $mySql = new MySql(
            $this->container->getHost(),
            $this->container->getMappedPort(self::PORT),
            self::DATABASE,
            self::USERNAME,
            self::PASSWORD
        );

        $mySql->initializeDatabase();
        $mySql->appendObserveItem(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowHash: null,
            messageSource: MessageInitMock::class,
            message: [
                'data' => 'test data',
            ]
        );

        $flowObserver = new FlowObserver($mySql);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_SCHEMA);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_INSTANCE);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_RUN);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_MESSAGE);
        $this->assertCount(5, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_QUEUE);
        $this->assertCount(0, iterator_to_array($stmt->fetchAll()));
    }
}
