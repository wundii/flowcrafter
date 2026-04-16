<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowEmptyMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\MySqlClientTestTrait;
use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\FlowObserver;
use Wundii\Flowcrafter\Storage\MySql;

final class FlowObserverMySqlTest extends TestCase
{
    use MySqlClientTestTrait;

    public function testRunObserverWithoutMessages(): void
    {
        $storage = $this->storage();
        $flowObserver = new FlowObserver($storage, []);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_QUEUE);
        $this->assertCount(0, iterator_to_array($stmt->fetchAll()));
    }

    public function testRunObserverWithMessages(): void
    {
        $storage = $this->storage();
        $storage->initializeDatabase();
        $storage->appendObserveItem(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowHash: null,
            messageSource: MessageInitMock::class,
            message: [
                'data' => 'test data',
            ]
        );

        $flowObserver = new FlowObserver($storage, []);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_SCHEMA);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_INSTANCE);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_RUN);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_MESSAGE);
        $this->assertCount(6, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_RESULT);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_QUEUE);
        $this->assertCount(0, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_EXCEPTION);
        $this->assertCount(0, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_SOURCE_STUB);
        $this->assertCount(4, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_SOURCE_MESSAGE);
        $this->assertCount(4, iterator_to_array($stmt->fetchAll()));
    }

    public function testRunObserverWithEmptyInitMessage(): void
    {
        $storage = $this->storage();
        $storage->initializeDatabase();
        $storage->appendObserveItem(
            type: 'flow.empty.v1',
            flowSource: WorkflowEmptyMock::class,
            flowHash: null,
            messageSource: EmptyInitMessage::class,
            message: null,
        );

        $flowObserver = new FlowObserver($storage, []);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_SCHEMA);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_INSTANCE);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_RUN);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_MESSAGE);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_RESULT);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_QUEUE);
        $this->assertCount(0, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_EXCEPTION);
        $this->assertCount(0, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_SOURCE_STUB);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_SOURCE_MESSAGE);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));
    }
}
