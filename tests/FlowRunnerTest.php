<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\DependencyConstructMock;
use Tests\MockClass\DependencyMock;
use Tests\MockClass\FailStubMock;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\NextStubMock;
use Tests\MockClass\OtherStubMock;
use Tests\MockClass\PostStubMock;
use Tests\MockClass\StubMock;
use Tests\MockClass\WorkflowBoolMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

final class FlowRunnerTest extends TestCase
{
    public function testRunReturnsMessageReturnInterface(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: '1234',
        );
        $result = $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertSame('1234', $flow->getSubject());

        $exceptions = $flow->getFlowExceptions();
        $runs = $flow->runs();
        $this->assertCount(0, $exceptions);
        $this->assertCount(1, $runs);

        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertStringStartsWith('[', $result->getData());
    }

    public function testRestartingAnWorkflow(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);

        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertStringStartsWith('[', $result->getData());

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(new MessageDataMock('test data round two'), $flow->getHash());

        $exceptions = $flow->getFlowExceptions();
        $runs = $flow->runs();
        $this->assertCount(0, $exceptions);
        $this->assertCount(1, $runs);

        $this->assertCount(5, $flowRunner->getFlow()->getFlowMessages());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertStringStartsWith('[', $result->getData());
    }

    public function testRunFail(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
        );

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        $flow = $flowRunner->getFlow();
        $exceptions = $flow->getFlowExceptions();
        $runs = $flow->runs();
        $this->assertCount(1, $exceptions);
        $this->assertCount(1, $runs);

        $exception = $exceptions[0];
        $this->assertInstanceOf(FlowException::class, $exception);
        $this->assertSame($flow->getHash(), $exception->getFlowHash());
        $this->assertSame($flow->getRuntimeHash(), $exception->getFlowRuntimeHash());
        $this->assertSame(FailStubMock::class, $exception->getStubSource());
        $this->assertStringStartsWith('Test Exception', $exception->getMessage());
    }

    public function testRunWithEmptyIncludeStubsExecutesAll(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(new MessageInitMock('test data'), includeStubs: []);

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
    }

    public function testRunWithIncludeStubsOnlyNextStub(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(
            new MessageInitMock('test data'),
            includeStubs: [StubMock::class, NextStubMock::class],
        );

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(2, $flow->getFlowMessages());
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertIsBool($result);
    }

    public function testRunWithIncludeStubsSkipNextStub(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(
            new MessageInitMock('test data'),
            includeStubs: [StubMock::class, OtherStubMock::class, PostStubMock::class],
        );

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(5, $flow->getFlowMessages());
        $this->assertCount(0, $flow->getFlowResults());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
    }

    public function testRunWithIncludeStubsSkipPostStub(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(
            new MessageInitMock('test data'),
            includeStubs: [StubMock::class, NextStubMock::class, OtherStubMock::class],
        );

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(3, $flow->getFlowMessages());
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertIsBool($result);
    }

    public function testRunWithDependencyInjection(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            dependenciesInjection: [
                DependencyMock::class,
                new DependencyConstructMock(' the end'),
            ]
        );
        $result = $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);

        $exceptions = $flow->getFlowExceptions();
        $runs = $flow->runs();
        $this->assertCount(0, $exceptions);
        $this->assertCount(1, $runs);

        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame(strtoupper($result->getData()), $result->getData());
        $this->assertStringEndsWith('THE END', $result->getData());
    }

    public function testRunWithoutMessageReturnInterface(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.bool.v1',
            flowSource: WorkflowBoolMock::class,
        );
        $result = $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(1, $flow->getFlowMessages());
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertTrue($flow->getFlowResults()[0]->getResult());
        $this->assertTrue($result);
    }
}
