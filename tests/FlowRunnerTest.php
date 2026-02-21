<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\EsdbClientTestTrait;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Storage\EventSourcingDB;

final class FlowRunnerTest extends TestCase
{
    use EsdbClientTestTrait;

    public function testRunReturnsMessageReturnInterface(): void
    {
        $eventSourcingDB = new EventSourcingDB(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $eventSourcingDB,
        );
        $result = $flowRunner->run(new MessageInitMock('test data'));

        // $eventTypesQl = 'FROM e IN events WHERE e.data.hash == "019c7c29-289a-70eb-b81b-123456789abc" PROJECT INTO e';
        // $eventTypesQl = 'FROM e IN events PROJECT INTO e';
        // $eventTypes = $this->client->runEventQlQuery($eventTypesQl);
        // dump(iterator_to_array($eventTypes));

        $this->assertCount(5, $flowRunner->getFlow()->getFlowMessages());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame('End of flow', $result->getData());
    }
}
