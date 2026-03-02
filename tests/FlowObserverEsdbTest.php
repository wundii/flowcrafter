<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\EsdbClientTestTrait;
use Thenativeweb\Eventsourcingdb\ReadEventsOptions;
use Wundii\Flowcrafter\FlowObserver;
use Wundii\Flowcrafter\Storage\Esdb;

final class FlowObserverEsdbTest extends TestCase
{
    use EsdbClientTestTrait;

    public function testRunObserverWithoutMessages(): void
    {
        $esdb = new Esdb(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );

        $flowObserver = new FlowObserver($esdb);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(0, iterator_to_array($events));
    }

    public function testRunObserverWithMessages(): void
    {
        $esdb = new Esdb(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );
        $esdb->appendObserveItem(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowHash: null,
            messageSource: MessageInitMock::class,
            message: [
                'data' => 'test data',
            ]
        );

        $flowObserver = new FlowObserver($esdb);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(9, iterator_to_array($events));

        $flowSchemaEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.schema.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowSchemaEvents));

        $flowInstanceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.instance.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowInstanceEvents));

        $flowRunEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.run.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowRunEvents));

        $flowMessageEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.message.v1" PROJECT INTO e');
        $this->assertCount(5, iterator_to_array($flowMessageEvents));
    }
}
