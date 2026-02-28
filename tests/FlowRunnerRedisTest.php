<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\RedisClientTestTrait;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\Redis;

final class FlowRunnerRedisTest extends TestCase
{
    use RedisClientTestTrait;

    public function testRunReturnsMessageReturnInterface(): void
    {
        $redis = new Redis(
            $this->container->getHost(),
            $this->container->getMappedPort(6379),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $redis,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $this->assertCount(5, $flowRunner->getFlow()->getFlowMessages());

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
