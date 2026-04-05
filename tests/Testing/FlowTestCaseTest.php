<?php

declare(strict_types=1);

namespace Tests\Testing;

use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\MockClass\BoolStubMock;
use Tests\MockClass\DependencyConstructMock;
use Tests\MockClass\DependencyMock;
use Tests\MockClass\FailStubMock;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageDataSecondMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageReturnMock;
use Tests\MockClass\MessageSubDataMock;
use Tests\MockClass\PostStubMock;
use Tests\MockClass\StubMock;
use Tests\MockClass\WorkflowBoolMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
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
            dependencies: [
                DependencyMock::class,
                new DependencyConstructMock(' end'),
            ],
        );

        $this->assertFlowOk();
        $this->assertNoFlowExceptions();
        $this->assertFlowRunCount(1);
        $this->assertFlowMessageCount(6);
        $this->assertFlowResultCount(1);
        $this->assertStubExecuted(StubMock::class);
        $this->assertStubExecuted(PostStubMock::class);
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
        $this->assertFlowResultCount(1);
    }

    public function testRunFlowFailed(): void
    {
        try {
            $this->runFlow(
                flowType: 'flow.workflow.fail.v1',
                flowSource: WorkflowFailMock::class,
                initMessage: new MessageInitMock('boom'),
            );
            self::fail('Expected RuntimeException from FailStubMock was not thrown.');
        } catch (RuntimeException $runtimeException) {
            $this->assertStringStartsWith('Test Exception', $runtimeException->getMessage());
        }

        $this->assertFlowFailed();
        $this->assertFlowExceptionFrom(FailStubMock::class);
        $this->assertFlowExceptionFrom(FailStubMock::class, 'Test Exception');
    }

    public function testRunFlowWithIncludeStubs(): void
    {
        $this->runFlow(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            initMessage: new MessageInitMock('partial'),
            includeStubs: [StubMock::class],
        );

        $this->assertStubExecuted(StubMock::class);
        $this->assertStubNotExecuted(PostStubMock::class);
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
        $this->assertFlowExceptionFrom(FailStubMock::class);
    }

    public function testRunStubIsolated(): void
    {
        $result = $this->runStub(
            stubSource: StubMock::class,
            messages: [new MessageInitMock('isolated')],
        );

        $this->assertInstanceOf(MessageDataMock::class, $result);
        $this->assertStringStartsWith('isolated mit ', $result->getData());
    }

    public function testRunStubBoolReturn(): void
    {
        $result = $this->runStub(
            stubSource: BoolStubMock::class,
            messages: [new MessageInitMock('x')],
        );

        $this->assertTrue($result);

        $resultEmpty = $this->runStub(
            stubSource: BoolStubMock::class,
            messages: [new MessageInitMock('')],
        );

        $this->assertFalse($resultEmpty);
    }

    public function testRunStubWithDependencies(): void
    {
        $result = $this->runStub(
            stubSource: PostStubMock::class,
            messages: [
                new MessageDataMock('first'),
                new MessageDataSecondMock('second', new MessageSubDataMock('sub')),
            ],
            dependencies: [
                DependencyMock::class,
                new DependencyConstructMock(' end'),
            ],
        );

        $this->assertInstanceOf(MessageReturnMock::class, $result);
        $this->assertStringEndsWith(' END', $result->getData());
    }
}
