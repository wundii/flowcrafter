<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowEmptyMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\RedisClientTestTrait;
use Wundii\Flowcrafter\DependencyInjection\DependencyRegistry;
use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\FlowObserver;

final class FlowObserverRedisTest extends TestCase
{
    use RedisClientTestTrait;

    public function testRunObserverWithoutMessages(): void
    {
        $storage = $this->storage();
        $queue = $this->queue();
        $flowObserver = new FlowObserver($storage, $queue, new DependencyRegistry());
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $events = $this->client->keys('flow:*');

        $this->assertCount(0, $events);
    }

    public function testRunObserverWithMessages(): void
    {
        $storage = $this->storage();
        $queue = $this->queue();
        $queue->appendObserveItem(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowHash: null,
            messageSource: MessageInitMock::class,
            message: [
                'data' => 'test data',
            ]
        );

        $flowObserver = new FlowObserver($storage, $queue, new DependencyRegistry());
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $events = $this->client->keys('flow:*');
        $this->assertCount(18, $events);

        $flowSchemaEvents = $this->client->keys('flow:schema:*');
        $this->assertCount(1, $flowSchemaEvents);

        $flowInstanceEvents = $this->client->keys('flow:instance:*');
        $this->assertCount(1, $flowInstanceEvents);

        $flowRunEvents = $this->client->keys('flow:run:*');
        $this->assertCount(1, $flowRunEvents);

        $flowMessageEvents = $this->client->keys('flow:message:*');
        $this->assertCount(6, $flowMessageEvents);

        $flowResultEvents = $this->client->keys('flow:result:*');
        $this->assertCount(1, $flowResultEvents);

        $flowExceptionEvents = $this->client->keys('flow:exception:*');
        $this->assertCount(0, $flowExceptionEvents);

        $flowStepSourceEvents = $this->client->keys('flow:source:step:*');
        $this->assertCount(4, $flowStepSourceEvents);

        $flowMessageSourceEvents = $this->client->keys('flow:source:message:*');
        $this->assertCount(4, $flowMessageSourceEvents);
    }

    public function testRunObserverWithEmptyInitMessage(): void
    {
        $storage = $this->storage();
        $queue = $this->queue();
        $queue->appendObserveItem(
            type: 'flow.empty.v1',
            flowSource: WorkflowEmptyMock::class,
            flowHash: null,
            messageSource: EmptyInitMessage::class,
            message: null,
        );

        $flowObserver = new FlowObserver($storage, $queue, new DependencyRegistry());
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $flowSchemaEvents = $this->client->keys('flow:schema:*');
        $this->assertCount(1, $flowSchemaEvents);

        $flowInstanceEvents = $this->client->keys('flow:instance:*');
        $this->assertCount(1, $flowInstanceEvents);

        $flowRunEvents = $this->client->keys('flow:run:*');
        $this->assertCount(1, $flowRunEvents);

        $flowMessageEvents = $this->client->keys('flow:message:*');
        $this->assertCount(1, $flowMessageEvents);

        $flowResultEvents = $this->client->keys('flow:result:*');
        $this->assertCount(1, $flowResultEvents);

        $flowExceptionEvents = $this->client->keys('flow:exception:*');
        $this->assertCount(0, $flowExceptionEvents);

        $flowStepSourceEvents = $this->client->keys('flow:source:step:*');
        $this->assertCount(1, $flowStepSourceEvents);

        $flowMessageSourceEvents = $this->client->keys('flow:source:message:*');
        $this->assertCount(1, $flowMessageSourceEvents);
    }
}
