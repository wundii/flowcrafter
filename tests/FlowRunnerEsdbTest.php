<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\EmptyWorkflowMock;
use Tests\MockClass\FailStubMock;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\EsdbClientTestTrait;
use Thenativeweb\Eventsourcingdb\ReadEventsOptions;
use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowRunner;

final class FlowRunnerEsdbTest extends TestCase
{
    use EsdbClientTestTrait;

    public function testRunReturnsMessageReturnInterface(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $this->storage(),
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $this->assertCount(6, $flowRunner->getFlow()->getFlowMessages());

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(18, iterator_to_array($events));

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

        $flowStubSourceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.source.stub.v1" PROJECT INTO e');
        $this->assertCount(4, iterator_to_array($flowStubSourceEvents));

        $flowMessageSourceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.source.message.v1" PROJECT INTO e');
        $this->assertCount(4, iterator_to_array($flowMessageSourceEvents));
    }

    // public function testRunTryRewriteSchema(): void
    // {
    //     $storage = $this->storage();
    //
    //     $flowRunner = new FlowRunner(
    //         type: 'flow.workflow.v1',
    //         flowSource: WorkflowMock::class,
    //         storage: $storage,
    //     );
    //     $flowRunner->run(new MessageInitMock('test data'));
    //
    //     $this->expectException(InvalidArgumentException::class);
    //
    //     $flow = $flowRunner->getFlow();
    //     $storage->registerFlowSchema($flow->getSchema());
    // }

    public function testRestartingAnWorkflow(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: '1234',
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertSame('1234', $flow->getSubject());
        $this->assertCount(6, $flow->getFlowMessages());

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(18, iterator_to_array($events));

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: 'fail',
            storage: $storage,
        );
        $flowRunner->run(new MessageDataMock('test data round two'), $flow->getHash());

        $this->assertCount(5, $flowRunner->getFlow()->getFlowMessages());
        $this->assertSame('1234', $flowRunner->getFlow()->getSubject());

        $events = $this->client->readEvents('/', new ReadEventsOptions(true));
        $this->assertCount(25, iterator_to_array($events));

        $flowSchemaEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.schema.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowSchemaEvents));

        $flowInstanceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.instance.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowInstanceEvents));

        $flowRunEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.run.v1" PROJECT INTO e');
        $this->assertCount(2, iterator_to_array($flowRunEvents));

        $flowMessageEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.message.v1" PROJECT INTO e');
        $this->assertCount(11, iterator_to_array($flowMessageEvents));

        $flowMessageEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.source.stub.v1" PROJECT INTO e');
        $this->assertCount(4, iterator_to_array($flowMessageEvents));

        $flowMessageSourceEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.source.message.v1" PROJECT INTO e');
        $this->assertCount(4, iterator_to_array($flowMessageSourceEvents));
    }

    public function testRunFail(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: $this->storage(),
        );

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        $flow = $flowRunner->getFlow();
        $exceptions = $flow->getFlowExceptions();
        $this->assertCount(1, $exceptions);

        $exception = $exceptions[0];
        $this->assertInstanceOf(FlowException::class, $exception);
        $this->assertSame($flow->getHash(), $exception->getFlowHash());
        $this->assertSame($flow->getRuntimeHash(), $exception->getFlowRuntimeHash());
        $this->assertSame(FailStubMock::class, $exception->getStubSource());
        $this->assertStringStartsWith('Test Exception', $exception->getMessage());
    }

    public function testRunWithEmptyInitMessage(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.empty.v1',
            flowSource: EmptyWorkflowMock::class,
            storage: $this->storage(),
        );
        $flowRunner->run(new EmptyInitMessage());

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);

        $this->assertCount(1, $flow->getFlowMessages());
        $this->assertSame(StatusEnum::OK, $flow->status());

        $flowMessageEvents = $this->client->runEventQlQuery('FROM e IN events WHERE e.type == "flowcrafter.flow.message.v1" PROJECT INTO e');
        $this->assertCount(1, iterator_to_array($flowMessageEvents));
    }
}
