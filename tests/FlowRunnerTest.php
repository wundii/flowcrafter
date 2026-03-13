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
        );
        $result = $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);

        $exceptions = $flow->getFlowExceptions();
        $runs = $flow->getFlowRuns();
        $this->assertCount(0, $exceptions);
        $this->assertCount(1, $runs);

        $this->assertCount(6, $flow->getFlowMessages());
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
        $runs = $flow->getFlowRuns();
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
        $runs = $flow->getFlowRuns();
        $this->assertCount(1, $exceptions);
        $this->assertCount(1, $runs);

        $exception = $exceptions[0];
        $this->assertInstanceOf(FlowException::class, $exception);
        $this->assertSame($flow->getHash(), $exception->getFlowHash());
        $this->assertSame($flow->getRuntimeHash(), $exception->getFlowRuntimeHash());
        $this->assertSame(FailStubMock::class, $exception->getStubSource());
        $this->assertStringStartsWith('Test Exception', $exception->getMessage());
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
        $runs = $flow->getFlowRuns();
        $this->assertCount(0, $exceptions);
        $this->assertCount(1, $runs);

        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame(strtoupper($result->getData()), $result->getData());
        $this->assertStringEndsWith('THE END', $result->getData());
    }
}
