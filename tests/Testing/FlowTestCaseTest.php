<?php

declare(strict_types=1);

namespace Tests\Testing;

use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\MockClass\BoolStepMock;
use Tests\MockClass\DependencyConstructMock;
use Tests\MockClass\DependencyMock;
use Tests\MockClass\FailStepMock;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageDataSecondMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageReturnMock;
use Tests\MockClass\MessageSubDataMock;
use Tests\MockClass\NextStepMock;
use Tests\MockClass\PostStepMock;
use Tests\MockClass\StepMock;
use Tests\MockClass\WorkflowBoolMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\DependencyInjection\DependencyRegistry;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\Testing\FlowTestCase;

final class FlowTestCaseTest extends FlowTestCase
{
    public function testRunFlowHappyPath(): void
    {
        $this->runFlow(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            initMessage: new MessageInitMock('test data'),
            flowSubject: 'subject-1',
            dependencyRegistry: (new DependencyRegistry())
                ->autowire(DependencyMock::class)
                ->instance(new DependencyConstructMock(' end')),
        );

        // NextStepMock returns a non-deterministic bool (random_int) — the flow
        // status therefore oscillates between OK and WARNING, we only assert the
        // absence of failures here.
        $this->assertNoFlowExceptions();
        $this->assertFlowRunCount(1);
        $this->assertFlowMessageCount(6);
        $this->assertFlowResultCount(1);
        $this->assertStepExecuted(StepMock::class);
        $this->assertStepExecuted(PostStepMock::class);
        $this->assertFlowHasMessage(MessageDataMock::class);

        $messageReturn = $this->assertFlowReturned(MessageReturnMock::class);
        $this->assertStringStartsWith('[', $messageReturn->getData());
        $this->assertSame('subject-1', $this->lastFlow()->getSubject());
    }

    public function testRunFlowBoolLeafResult(): void
    {
        $result = $this->runFlow(
            flowType: 'flow.workflow.bool.v1',
            flowSource: WorkflowBoolMock::class,
            initMessage: new MessageInitMock('has data'),
        );

        $this->assertTrue($result);
        $this->assertFlowStatus(StatusEnum::OK);
        $this->assertFlowBoolResult(true);
        $this->assertFlowBoolResultFrom(BoolStepMock::class, true);
        $this->assertFlowResultCount(1);
    }

    public function testAssertFlowBoolResultFromFailsWhenStepNotFound(): void
    {
        $this->runFlow(
            flowType: 'flow.workflow.bool.v1',
            flowSource: WorkflowBoolMock::class,
            initMessage: new MessageInitMock('has data'),
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected a FlowResult from step');
        $this->assertFlowBoolResultFrom(FailStepMock::class, true);
    }

    public function testAssertFlowBoolResultFromFailsOnWrongBool(): void
    {
        $this->runFlow(
            flowType: 'flow.workflow.bool.v1',
            flowSource: WorkflowBoolMock::class,
            initMessage: new MessageInitMock('has data'),
        );

        $this->expectException(AssertionFailedError::class);
        $this->assertFlowBoolResultFrom(BoolStepMock::class, false);
    }

    public function testRunFlowFailed(): void
    {
        try {
            $this->runFlow(
                flowType: 'flow.workflow.fail.v1',
                flowSource: WorkflowFailMock::class,
                initMessage: new MessageInitMock('boom'),
            );
            self::fail('Expected RuntimeException from FailStepMock was not thrown.');
        } catch (RuntimeException $runtimeException) {
            $this->assertStringStartsWith('Test Exception', $runtimeException->getMessage());
        }

        $this->assertFlowFailed();
        $this->assertFlowExceptionFrom(FailStepMock::class);
        $this->assertFlowExceptionFrom(FailStepMock::class, 'Test Exception');
    }

    public function testRunFlowWithIncludeSteps(): void
    {
        // NextStepMock has no return types (leaf), so expansion adds nothing downstream.
        // OtherStepMock and PostStepMock are not reachable from NextStepMock.
        $this->runFlow(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            initMessage: new MessageDataMock('partial'),
            includeSteps: [NextStepMock::class],
        );

        $this->assertStepExecuted(NextStepMock::class);
        $this->assertStepNotExecuted(PostStepMock::class);
    }

    public function testLastResultAndLastFlowThrowWhenNoRun(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No flow has been run yet');

        $this->lastFlow();
    }

    public function testAssertFlowOkFailsOnFailedFlow(): void
    {
        try {
            $this->runFlow(
                flowType: 'flow.workflow.fail.v1',
                flowSource: WorkflowFailMock::class,
                initMessage: new MessageInitMock('x'),
            );
        } catch (RuntimeException) {
            // expected — continue to the assertion we want to exercise
        }

        $this->expectException(AssertionFailedError::class);
        $this->assertFlowOk();
    }

    public function testAssertFlowExceptionFromFailsWhenNoMatch(): void
    {
        $this->runFlow(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            initMessage: new MessageInitMock('ok'),
        );

        $this->expectException(AssertionFailedError::class);
        $this->assertFlowExceptionFrom(FailStepMock::class);
    }

    public function testRunStepIsolated(): void
    {
        $result = $this->runStep(
            stepSource: StepMock::class,
            messages: [new MessageInitMock('isolated')],
        );

        $this->assertInstanceOf(MessageDataMock::class, $result);
        $this->assertStringStartsWith('isolated mit ', $result->getData());
    }

    public function testRunStepBoolReturn(): void
    {
        $result = $this->runStep(
            stepSource: BoolStepMock::class,
            messages: [new MessageInitMock('x')],
        );

        $this->assertTrue($result);

        $resultEmpty = $this->runStep(
            stepSource: BoolStepMock::class,
            messages: [new MessageInitMock('')],
        );

        $this->assertFalse($resultEmpty);
    }

    public function testRunStepWithDependencies(): void
    {
        $result = $this->runStep(
            stepSource: PostStepMock::class,
            messages: [
                new MessageDataMock('first'),
                new MessageDataSecondMock('second', new MessageSubDataMock('sub')),
            ],
            dependencyRegistry: (new DependencyRegistry())
                ->autowire(DependencyMock::class)
                ->instance(new DependencyConstructMock(' end')),
        );

        $this->assertInstanceOf(MessageReturnMock::class, $result);
        $this->assertStringEndsWith(' END', $result->getData());
    }
}
