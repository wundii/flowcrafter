<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\RedisClientTestTrait;
use Wundii\Flowcrafter\FlowObserver;
use Wundii\Flowcrafter\Storage\Redis;

final class FlowObserverRedisTest extends TestCase
{
    use RedisClientTestTrait;

    public function testRunObserverWithoutMessages(): void
    {
        $redis = new Redis(
            $this->container->getHost(),
            $this->container->getMappedPort(6379),
        );

        $flowObserver = new FlowObserver($redis);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $events = $this->client->keys('flow:*');

        $keyId = array_search('flow:queue', $events, true);

        if (is_numeric($keyId)) {
            unset($events[$keyId]);
        }

        $this->assertCount(0, $events);
    }

    public function testRunObserverWithMessages(): void
    {
        $redis = new Redis(
            $this->container->getHost(),
            $this->container->getMappedPort(6379),
        );

        $redis->addObserveItem(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowHash: null,
            messageSource: MessageInitMock::class,
            message: [
                'data' => 'test data',
            ]
        );

        $flowObserver = new FlowObserver($redis);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $events = $this->client->keys('flow:*');
        $this->assertCount(8, $events);

        $flowSchemaEvents = $this->client->keys('flow:schema:*');
        $this->assertCount(1, $flowSchemaEvents);

        $flowInstanceEvents = $this->client->keys('flow:instance:*');
        $this->assertCount(1, $flowInstanceEvents);

        $flowRunEvents = $this->client->keys('flow:run:*');
        $this->assertCount(1, $flowRunEvents);

        $flowMessageEvents = $this->client->keys('flow:message:*');
        $this->assertCount(5, $flowMessageEvents);
    }
}
