<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\EsdbClientTestTrait;
use Thenativeweb\Eventsourcingdb\EventCandidate;
use Thenativeweb\Eventsourcingdb\ReadEventsOptions;
use Wundii\Flowcrafter\FlowObserver;
use Wundii\Flowcrafter\Storage\EventSourcingDB;

final class FlowObserverEsdbTest extends TestCase
{
    use EsdbClientTestTrait;

    public function testRunObserverWithoutMessages(): void
    {
        $eventSourcingDB = new EventSourcingDB(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );

        $flowObserver = new FlowObserver($eventSourcingDB);
        $flowObserver->run(maxExecutionTimeInSeconds: 0.5);

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(0, iterator_to_array($events));
    }

    public function testRunObserverWithMessages(): void
    {
        $eventSourcingDB = new EventSourcingDB(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );

        $message = [
            'type' => 'flow.workflow.v1',
            'flowSource' => WorkflowMock::class,
            'flowHash' => null,
            'messageSource' => MessageInitMock::class,
            'message' => [
                'data' => 'test data',
            ],
        ];

        $this->client->writeEvents([
            new EventCandidate(
                source: EventSourcingDB::SOURCE,
                subject: EventSourcingDB::QUEUE_SUBJECT,
                type: 'flowcrafter.flow.queue.v1',
                data: $message,
            ),
        ]);

        $flowObserver = new FlowObserver($eventSourcingDB);
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
