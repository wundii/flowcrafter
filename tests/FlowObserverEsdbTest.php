<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowEmptyMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\EsdbClientTestTrait;
use Thenativeweb\Eventsourcingdb\ReadEventsOptions;
use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\FlowObserver;

final class FlowObserverEsdbTest extends TestCase
{
    use EsdbClientTestTrait;

    public function testRunObserverWithoutMessages(): void
    {
        $storage = $this->storage();
        $flowObserver = new FlowObserver($storage, []);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(0, iterator_to_array($events));
    }

    public function testRunObserverWithMessages(): void
    {
        $storage = $this->storage();
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

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(20, iterator_to_array($events));

        $flowSchemaEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.schema.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowSchemaEvents));

        $flowInstanceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.instance.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowInstanceEvents));

        $flowRunEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.run.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowRunEvents));

        $flowMessageEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.message.v1" PROJECT INTO e');
        $this->assertCount(6, iterator_to_array($flowMessageEvents));

        $flowResultEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.result.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowResultEvents));

        $flowExceptionEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.exception.v1" PROJECT INTO e');
        $this->assertCount(0, iterator_to_array($flowExceptionEvents));

        $flowStepSourceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.source.step.v1" PROJECT INTO e');
        $this->assertCount(4, iterator_to_array($flowStepSourceEvents));

        $flowMessageSourceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.source.message.v1" PROJECT INTO e');
        $this->assertCount(4, iterator_to_array($flowMessageSourceEvents));
    }

    public function testRunObserverWithEmptyInitMessage(): void
    {
        $storage = $this->storage();
        $storage->appendObserveItem(
            type: 'flow.empty.v1',
            flowSource: WorkflowEmptyMock::class,
            flowHash: null,
            messageSource: EmptyInitMessage::class,
            message: null,
        );

        $flowObserver = new FlowObserver($storage, []);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $flowSchemaEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.schema.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowSchemaEvents));

        $flowInstanceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.instance.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowInstanceEvents));

        $flowRunEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.run.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowRunEvents));

        $flowMessageEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.message.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowMessageEvents));

        $flowResultEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.result.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowResultEvents));

        $flowExceptionEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.exception.v1" PROJECT INTO e');
        $this->assertCount(0, iterator_to_array($flowExceptionEvents));

        $flowStepSourceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.source.step.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowStepSourceEvents));

        $flowMessageSourceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.source.message.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowMessageSourceEvents));
    }
}
