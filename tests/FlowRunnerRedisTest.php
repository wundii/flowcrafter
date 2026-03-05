<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\RedisClientTestTrait;
use Wundii\Flowcrafter\FlowRunner;

final class FlowRunnerRedisTest extends TestCase
{
    use RedisClientTestTrait;

    public function testRunReturnsMessageReturnInterface(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
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
