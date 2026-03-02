<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\EsdbClientTestTrait;
use Thenativeweb\Eventsourcingdb\ReadEventsOptions;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\Esdb;

final class FlowRunnerEsdbTest extends TestCase
{
    use EsdbClientTestTrait;

    public function testRunReturnsMessageReturnInterface(): void
    {
        $esdb = new Esdb(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $esdb,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $this->assertCount(5, $flowRunner->getFlow()->getFlowMessages());

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(8, iterator_to_array($events));

        $flowSchemaEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.schema.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowSchemaEvents));

        $flowInstanceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.instance.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowInstanceEvents));

        $flowRunEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.run.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowRunEvents));

        $flowMessageEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.message.v1" PROJECT INTO e');
        $this->assertCount(5, iterator_to_array($flowMessageEvents));
    }

    public function testRestartingAnWorkflow(): void
    {
        $esdb = new Esdb(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $esdb,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);

        $this->assertCount(5, $flowRunner->getFlow()->getFlowMessages());

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(8, iterator_to_array($events));

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $esdb,
        );
        $flowRunner->run(new MessageDataMock('test data round two'), $flow->getHash());

        $this->assertCount(4, $flowRunner->getFlow()->getFlowMessages());

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(13, iterator_to_array($events));

        $flowSchemaEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.schema.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowSchemaEvents));

        $flowInstanceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.instance.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowInstanceEvents));

        $flowRunEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.run.v1" PROJECT INTO e');
        $this->assertCount(2, iterator_to_array($flowRunEvents));

        $flowMessageEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.message.v1" PROJECT INTO e');
        $this->assertCount(9, iterator_to_array($flowMessageEvents));
    }
}
