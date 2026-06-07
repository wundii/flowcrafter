<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\DependencyConstructMock;
use Tests\MockClass\DependencyMock;
use Tests\MockClass\FailStepMock;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageReturnMock;
use Tests\MockClass\NextStepMock;
use Tests\MockClass\OtherStepMock;
use Tests\MockClass\PostStepMock;
use Tests\MockClass\RetryStepMock;
use Tests\MockClass\RunOnceStepMock;
use Tests\MockClass\StepMock;
use Tests\MockClass\WorkflowBoolMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
use Tests\MockClass\WorkflowRetryMock;
use Tests\MockClass\WorkflowRunOnceMock;
use Wundii\Flowcrafter\DependencyInjection\DependencyRegistry;
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
        $this->assertSame(FailStepMock::class, $exception->getStepSource());
        $this->assertStringStartsWith('Test Exception', $exception->getMessage());
    }

    public function testRunWithEmptyIncludeStepsExecutesAll(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(new MessageInitMock('test data'), includeSteps: []);

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
    }

    public function testRunWithIncludeStepsOnlyNextStep(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(
            new MessageInitMock('test data'),
            includeSteps: [StepMock::class, NextStepMock::class],
        );

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        // StepMock is filtered in at the top level; all downstream steps run unconditionally
        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
    }

    public function testRunWithIncludeStepsSkipNextStep(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(
            new MessageInitMock('test data'),
            includeSteps: [StepMock::class, OtherStepMock::class, PostStepMock::class],
        );

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        // StepMock is filtered in at the top level; all downstream steps run unconditionally
        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
    }

    public function testRunWithIncludeStepsSkipPostStep(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(
            new MessageInitMock('test data'),
            includeSteps: [StepMock::class, NextStepMock::class, OtherStepMock::class],
        );

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        // StepMock is filtered in at the top level; all downstream steps run unconditionally
        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
    }

    public function testRunWithIncludeStepsExcludingEntryStepRunsNothing(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(
            new MessageInitMock('test data'),
            includeSteps: [NextStepMock::class, OtherStepMock::class, PostStepMock::class],
        );

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        // StepMock (the only consumer of MessageInitMock) is not in includeSteps → nothing runs
        $this->assertCount(0, $flow->getFlowMessages());
        $this->assertCount(0, $flow->getFlowResults());
        $this->assertFalse($result);
    }

    public function testRunWithIncludeStepsExcludingEntryStepRuns(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(
            new MessageDataMock('test data'),
            includeSteps: [NextStepMock::class, OtherStepMock::class, PostStepMock::class],
        );

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(5, $flow->getFlowMessages());
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertInstanceOf(MessageReturnMock::class, $result);
    }

    public function testRunWithIncludeNextSteps(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $flowRunner->run(
            new MessageDataMock('test data'),
            includeSteps: [NextStepMock::class],
        );

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(1, $flow->getFlowMessages());
        $this->assertCount(1, $flow->getFlowResults());
    }

    public function testRunWithIncludeOtherSteps(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(
            new MessageDataMock('test data'),
            includeSteps: [OtherStepMock::class],
        );

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(4, $flow->getFlowMessages());
        $this->assertCount(0, $flow->getFlowResults());
        $this->assertInstanceOf(MessageReturnMock::class, $result);
    }

    public function testRunWithDependencyInjection(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            dependencyRegistry: (new DependencyRegistry())
                ->autowire(DependencyMock::class)
                ->instance(new DependencyConstructMock(' the end')),
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

    public function testCreateInstanceWithoutRunThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Container is not initialized');

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );

        $flowRunner->createInstance(StepMock::class, []);
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

    public function testRunRetrySuccess(): void
    {
        RetryStepMock::configure(failUntil: 2);

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.retry.v2',
            flowSource: WorkflowRetryMock::class,
        );
        $result = $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame(3, RetryStepMock::getCallCount());

        $flowRetries = $flow->getFlowRetries();
        $this->assertCount(2, $flowRetries);
        $this->assertSame(1, $flowRetries[0]->getAttempt());
        $this->assertSame('Retry attempt 1', $flowRetries[0]->getMessage());
        $this->assertSame(RetryStepMock::class, $flowRetries[0]->getStepSource());
        $this->assertSame(2, $flowRetries[1]->getAttempt());
        $this->assertSame('Retry attempt 2', $flowRetries[1]->getMessage());

        RetryStepMock::reset();
    }

    public function testRunRetryExhausted(): void
    {
        RetryStepMock::configure(failUntil: 10);

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.retry.v2',
            flowSource: WorkflowRetryMock::class,
        );

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
            $this->assertSame('Retry attempt 4', $exception->getMessage());
        }

        $flow = $flowRunner->getFlow();
        $this->assertCount(1, $flow->getFlowExceptions());
        $this->assertSame(RetryStepMock::class, $flow->getFlowExceptions()[0]->getStepSource());
        $this->assertSame(4, RetryStepMock::getCallCount());

        $flowRetries = $flow->getFlowRetries();
        $this->assertCount(3, $flowRetries);
        $this->assertSame(1, $flowRetries[0]->getAttempt());
        $this->assertSame(2, $flowRetries[1]->getAttempt());
        $this->assertSame(3, $flowRetries[2]->getAttempt());

        RetryStepMock::reset();
    }

    public function testRunOnceWithoutStorageExecutesNormally(): void
    {
        RunOnceStepMock::reset();

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.runonce.v1',
            flowSource: WorkflowRunOnceMock::class,
        );
        $result = $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame(1, RunOnceStepMock::getCallCount());

        RunOnceStepMock::reset();
    }
}
