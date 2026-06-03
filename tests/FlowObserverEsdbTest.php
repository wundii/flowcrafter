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
        $queue = $this->queue();
        $flowObserver = new FlowObserver($storage, $queue, []);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(0, iterator_to_array($events));
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

        $flowObserver = new FlowObserver($storage, $queue, []);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(20, iterator_to_array($events));

        $flowSchemaEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.schema.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowSchemaEvents));

        $flowInstanceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.instance.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowInstanceEvents));

        $flowRunEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.run.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowRunEvents));

        $flowMessageEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.message.v1" PROJECT INTO e');
        $this->assertCount(6, iterator_to_array($flowMessageEvents));

        $flowResultEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.result.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowResultEvents));

        $flowExceptionEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.exception.v1" PROJECT INTO e');
        $this->assertCount(0, iterator_to_array($flowExceptionEvents));

        $flowStepSourceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.source.step.v1" PROJECT INTO e');
        $this->assertCount(4, iterator_to_array($flowStepSourceEvents));

        $flowMessageSourceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.source.message.v1" PROJECT INTO e');
        $this->assertCount(4, iterator_to_array($flowMessageSourceEvents));
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

        $flowObserver = new FlowObserver($storage, $queue, []);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $flowSchemaEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.schema.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowSchemaEvents));

        $flowInstanceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.instance.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowInstanceEvents));

        $flowRunEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.run.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowRunEvents));

        $flowMessageEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.message.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowMessageEvents));

        $flowResultEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.result.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowResultEvents));

        $flowExceptionEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.exception.v1" PROJECT INTO e');
        $this->assertCount(0, iterator_to_array($flowExceptionEvents));

        $flowStepSourceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.source.step.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowStepSourceEvents));

        $flowMessageSourceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.source.message.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowMessageSourceEvents));
    }
}
