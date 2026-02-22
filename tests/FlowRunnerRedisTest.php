<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\RedisClientTestTrait;
use Thenativeweb\Eventsourcingdb\ReadEventsOptions;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\EventSourcingDB;
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

        $flowSchemaEvent = $this->client->keys('flow:schema:*');
        $this->assertCount(1, iterator_to_array($flowSchemaEvent));

        $flowSchemaEvent = $this->client->keys('flow:instance:*');
        $this->assertCount(1, iterator_to_array($flowSchemaEvent));

        $flowSchemaEvent = $this->client->keys('flow:run:*');
        $this->assertCount(1, iterator_to_array($flowSchemaEvent));

        $flowSchemaEvent = $this->client->keys('flow:message:*');
        $this->assertCount(5, iterator_to_array($flowSchemaEvent));
    }

    // public function testRestartingAnWorkflow(): void
    // {
    //     $eventSourcingDB = new EventSourcingDB(
    //         $this->container->getBaseUrl(),
    //         $this->container->getApiToken(),
    //     );
    //     $flowRunner = new FlowRunner(
    //         type: 'flow.workflow.v1',
    //         flowSource: WorkflowMock::class,
    //         storage: $eventSourcingDB,
    //     );
    //     $flowRunner->run(new MessageInitMock('test data'));
    //
    //     $flow = $flowRunner->getFlow();
    //     $this->assertInstanceOf(Flow::class, $flow);
    //
    //     $this->assertCount(5, $flowRunner->getFlow()->getFlowMessages());
    //
    //     $events = $this->client->readEvents('/', new ReadEventsOptions(true));
    //     $this->assertCount(8, iterator_to_array($events));
    //
    //     $flowRunner = new FlowRunner(
    //         type: 'flow.workflow.v1',
    //         flowSource: WorkflowMock::class,
    //         storage: $eventSourcingDB,
    //     );
    //     $flowRunner->run(new MessageDataMock('test data round two'), $flow->getHash());
    //
    //     $this->assertCount(4, $flowRunner->getFlow()->getFlowMessages());
    //
    //     $events = $this->client->readEvents('/', new ReadEventsOptions(true));
    //     $this->assertCount(13, iterator_to_array($events));
    //
    //     $flowSchemaEvent = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.schema.v1" PROJECT INTO e');
    //     $this->assertCount(1, iterator_to_array($flowSchemaEvent));
    //
    //     $flowSchemaEvent = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.v1" PROJECT INTO e');
    //     $this->assertCount(1, iterator_to_array($flowSchemaEvent));
    //
    //     $flowSchemaEvent = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.run.v1" PROJECT INTO e');
    //     $this->assertCount(2, iterator_to_array($flowSchemaEvent));
    //
    //     $flowSchemaEvent = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.message.v1" PROJECT INTO e');
    //     $this->assertCount(9, iterator_to_array($flowSchemaEvent));
    // }
}
