<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageDataSecondMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageReturnMock;
use Tests\MockClass\MessageSubDataMock;
use Tests\MockClass\NextStepMock;
use Tests\MockClass\OtherStepMock;
use Tests\MockClass\PostStepMock;
use Tests\MockClass\StepMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Converter;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowResult;
use Wundii\Flowcrafter\Source;

final class FlowStatusTest extends TestCase
{
    public function testInProgressWithNoRuns(): void
    {
        $flow = $this->createFlow();

        $this->assertSame(StatusEnum::IN_PROGRESS, $flow->status());
    }

    public function testInProgressWithRunButNoLeafMessages(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $this->assertSame(StatusEnum::IN_PROGRESS, $flow->status());
    }

    public function testOkWhenAllLeafsReached(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class));

        $this->assertSame(StatusEnum::OK, $flow->status());
    }

    public function testInProgressWhenOnlyOneLeafReached(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class));

        $this->assertSame(StatusEnum::IN_PROGRESS, $flow->status());
    }

    public function testMessagesFromPreviousRunDoNotCountAsLeafReached(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class, 'old-runtime-hash'));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class, 'old-runtime-hash'));

        $this->assertSame(StatusEnum::IN_PROGRESS, $flow->status());
    }

    public function testInProgressExceededWhenRunOlderThanOneHour(): void
    {
        $time = new DateTimeImmutable('-2 hours');
        $flow = $this->createFlow($time);
        $flow->addRun(null, $time);

        $this->assertSame(StatusEnum::IN_PROGRESS_EXCEEDED, $flow->status());
    }

    public function testInProgressNotExceededWhenRunIsRecent(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $this->assertSame(StatusEnum::IN_PROGRESS, $flow->status());
    }

    public function testOkNotExceededWhenAllLeafsReachedAfterOneHour(): void
    {
        $time = new DateTimeImmutable('-2 hours');
        $flow = $this->createFlow($time);
        $flow->addRun(null, $time);
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class));

        $this->assertSame(StatusEnum::OK, $flow->status());
    }

    public function testWarningWhenFlowResultIsFalse(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class));
        $flow->addResult(FlowResult::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $flow->getRuntimeHash(),
            stepSource: NextStepMock::class,
            stepHash: 'step-hash',
            result: false,
        ));

        $this->assertSame(StatusEnum::WARNING, $flow->status());
    }

    public function testOkWhenFlowResultIsTrue(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class));
        $flow->addResult(FlowResult::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $flow->getRuntimeHash(),
            stepSource: NextStepMock::class,
            stepHash: 'step-hash',
            result: true,
        ));

        $this->assertSame(StatusEnum::OK, $flow->status());
    }

    public function testFlowResultFalseDoesNotApplyWhenLeafsNotDone(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class));
        $flow->addResult(FlowResult::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $flow->getRuntimeHash(),
            stepSource: NextStepMock::class,
            stepHash: 'step-hash',
            result: false,
        ));

        $this->assertSame(StatusEnum::IN_PROGRESS, $flow->status());
    }

    public function testFlowResultFromPreviousRunDoesNotApply(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class));
        $flow->addResult(FlowResult::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: 'old-runtime-hash',
            stepSource: NextStepMock::class,
            stepHash: 'step-hash',
            result: false,
        ));

        $this->assertSame(StatusEnum::OK, $flow->status());
    }

    public function testFailedWhenExceptionInLastRun(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->addException(FlowException::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $flow->getRuntimeHash(),
            flowType: 'flow.workflow.v1',
            stepSource: NextStepMock::class,
            stepHash: 'step-hash',
            code: 500,
            message: 'something went wrong',
            file: __FILE__,
            line: __LINE__,
            traceString: '',
        ));

        $this->assertSame(StatusEnum::FAILED, $flow->status());
    }

    public function testExceptionInPreviousRunDoesNotCauseFailed(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class));
        $flow->addException(FlowException::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: 'old-runtime-hash',
            flowType: 'flow.workflow.v1',
            stepSource: NextStepMock::class,
            stepHash: 'step-hash',
            code: 500,
            message: 'old exception',
            file: __FILE__,
            line: __LINE__,
            traceString: '',
        ));

        $this->assertSame(StatusEnum::OK, $flow->status());
    }

    public function testFailedOverridesWarning(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class));
        $flow->addResult(FlowResult::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $flow->getRuntimeHash(),
            stepSource: NextStepMock::class,
            stepHash: 'step-hash',
            result: false,
        ));
        $flow->addException(FlowException::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $flow->getRuntimeHash(),
            flowType: 'flow.workflow.v1',
            stepSource: PostStepMock::class,
            stepHash: 'step-hash',
            code: 500,
            message: 'fatal error',
            file: __FILE__,
            line: __LINE__,
            traceString: '',
        ));

        $this->assertSame(StatusEnum::FAILED, $flow->status());
    }

    public function testIncludeStepsFiltersIrrelevantLeaf(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->setIncludeSteps([NextStepMock::class]);
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class));

        $this->assertSame(StatusEnum::OK, $flow->status());
    }

    public function testIncludeStepsWithoutLeafInLastRunIsOk(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->setIncludeSteps([OtherStepMock::class]);
        $flow->addMessage($this->makeMessage($flow, OtherStepMock::class));

        $this->assertSame(StatusEnum::OK, $flow->status());
    }

    public function testIncludeStepsRelevantLeafReachedViaAndJoinInput(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->setIncludeSteps([OtherStepMock::class]);
        $flow->addMessage($this->makeMessage($flow, OtherStepMock::class));
        $flow->addMessage(FlowMessage::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $flow->getRuntimeHash(),
            flowType: $flow->getType(),
            stepSource: PostStepMock::class,
            stepHash: 'step-hash',
            messageTypeEnum: MessageTypeEnum::FINISH,
            messageHash: Source::message(MessageDataSecondMock::class)->messageHash,
            message: new MessageDataSecondMock('test', new MessageSubDataMock('alien')),
            predecessorHash: null,
        ));

        $this->assertSame(StatusEnum::OK, $flow->status());
    }

    public function testIncludeStepsWithoutIncludeStepsChecksBothLeafs(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class));

        $this->assertSame(StatusEnum::IN_PROGRESS, $flow->status());
    }

    public function testOkWhenMissingLeafsWereReachedInPreviousRuns(): void
    {
        $flowHash = '019d429e-d1d1-7029-b178-4372d2f8dedc';
        $run1Hash = '019d429e-d1d1-7029-b178-4372d35ed9cc';
        $run2Hash = '019d42a9-75ff-7261-8ba6-4e74bd8b5dd7';
        $run3Hash = '019d42ac-5077-71b6-ae20-f80eb9d10522';
        $run4Hash = '019d42c7-ed20-71bb-9374-17636a3f37a6';
        $run5Hash = '019d431d-98fb-71aa-8b04-47c19cafa1d9';

        $flow = Converter::arrayToFlow([
            'flowSource' => WorkflowMock::class,
            'flowSubject' => 'order-1250',
            'flowType' => 'flow.workflow.v1',
            'flowSchema' => [
                'type' => 'flow.workflow.v1',
                'steps' => [
                    [
                        'source' => StepMock::class,
                        'messageEnum' => 'init',
                        'messages' => [MessageInitMock::class],
                        'returnTypes' => [MessageDataMock::class],
                    ],
                    [
                        'source' => NextStepMock::class,
                        'messageEnum' => 'step',
                        'messages' => [MessageDataMock::class],
                        'returnTypes' => [],
                    ],
                    [
                        'source' => OtherStepMock::class,
                        'messageEnum' => 'step',
                        'messages' => [MessageDataMock::class],
                        'returnTypes' => [MessageDataSecondMock::class],
                    ],
                    [
                        'source' => PostStepMock::class,
                        'messageEnum' => 'step',
                        'messages' => [MessageDataMock::class, MessageDataSecondMock::class],
                        'returnTypes' => [MessageReturnMock::class],
                    ],
                ],
            ],
            'flowSchemaHash' => '94faf9eb02cde769995368df5f8b2125',
            'flowHash' => $flowHash,
            'time' => '2026-03-31T06:39:57.000+00:00',
            'flowMessages' => [
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run4Hash,
                    'stepSource' => NextStepMock::class,
                    'stepHash' => '4b8cb9b50c0221f29b01127972094971',
                    'messageType' => 'finish',
                    'messageSource' => MessageDataMock::class,
                    'message' => [
                        'data' => 'Tiger mit Zwiebeln',
                    ],
                    'time' => '2026-03-31T07:24:51.882+00:00',
                    'hash' => '019d42c7-ed2a-70ec-b43a-9a28a40e22e1',
                    'predecessorHash' => null,
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run1Hash,
                    'stepSource' => PostStepMock::class,
                    'stepHash' => 'ff59175d198b078258ce53be2142d063',
                    'messageType' => 'finish',
                    'messageSource' => MessageDataSecondMock::class,
                    'message' => [
                        'data' => 'Tiger mit Zwiebeln, gebraten mit einer Prise Liebe',
                    ],
                    'time' => '2026-03-31T06:39:57.913+00:00',
                    'hash' => '019d429e-d1d9-7158-80a8-420ebb22cf4a',
                    'predecessorHash' => '019d429e-d1d8-7284-9b0e-91250fc566c9',
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run1Hash,
                    'stepSource' => NextStepMock::class,
                    'stepHash' => '4b8cb9b50c0221f29b01127972094971',
                    'messageType' => 'finish',
                    'messageSource' => MessageDataMock::class,
                    'message' => [
                        'data' => 'Tiger mit Zwiebeln',
                    ],
                    'time' => '2026-03-31T06:39:57.910+00:00',
                    'hash' => '019d429e-d1d6-73ad-a64b-621836e15f17',
                    'predecessorHash' => '019d429e-d1d5-7215-a59c-281b39405b2b',
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run1Hash,
                    'stepSource' => PostStepMock::class,
                    'stepHash' => 'ff59175d198b078258ce53be2142d063',
                    'messageType' => 'finish',
                    'messageSource' => MessageDataMock::class,
                    'message' => [
                        'data' => 'Tiger mit Zwiebeln',
                    ],
                    'time' => '2026-03-31T06:39:57.913+00:00',
                    'hash' => '019d429e-d1d9-7158-80a8-420ebc219b96',
                    'predecessorHash' => '019d429e-d1d5-7215-a59c-281b39405b2b',
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run1Hash,
                    'stepSource' => OtherStepMock::class,
                    'stepHash' => '0c424a4b88caeb050472b7dc8d4cc522',
                    'messageType' => 'finish',
                    'messageSource' => MessageDataMock::class,
                    'message' => [
                        'data' => 'Tiger mit Zwiebeln',
                    ],
                    'time' => '2026-03-31T06:39:57.912+00:00',
                    'hash' => '019d429e-d1d8-7284-9b0e-91250fc566c9',
                    'predecessorHash' => '019d429e-d1d5-7215-a59c-281b39405b2b',
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run2Hash,
                    'stepSource' => NextStepMock::class,
                    'stepHash' => '4b8cb9b50c0221f29b01127972094971',
                    'messageType' => 'finish',
                    'messageSource' => MessageDataMock::class,
                    'message' => [
                        'data' => 'Tiger mit Zwiebeln',
                    ],
                    'time' => '2026-03-31T06:51:35.305+00:00',
                    'hash' => '019d42a9-7609-7154-9254-19ee50f7b0d6',
                    'predecessorHash' => null,
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run1Hash,
                    'stepSource' => PostStepMock::class,
                    'stepHash' => 'ff59175d198b078258ce53be2142d063',
                    'messageType' => 'finish',
                    'messageSource' => MessageReturnMock::class,
                    'message' => [
                        'data' => '[Chef-Kuss] Tiger mit Zwiebeln | Tiger mit Zwiebeln, gebraten mit einer Prise Liebe',
                        'test' => 'Zubereitet in 42 Minuten',
                    ],
                    'time' => '2026-03-31T06:39:57.914+00:00',
                    'hash' => '019d429e-d1da-7129-8da1-4d39ffa62a7c',
                    'predecessorHash' => '019d429e-d1d9-7158-80a8-420ebc219b96',
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run3Hash,
                    'stepSource' => NextStepMock::class,
                    'stepHash' => '4b8cb9b50c0221f29b01127972094971',
                    'messageType' => 'finish',
                    'messageSource' => MessageDataMock::class,
                    'message' => [
                        'data' => 'Tiger mit Zwiebeln',
                    ],
                    'time' => '2026-03-31T06:54:42.304+00:00',
                    'hash' => '019d42ac-5080-7074-8d79-e0b19dccb7bc',
                    'predecessorHash' => null,
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run1Hash,
                    'stepSource' => StepMock::class,
                    'stepHash' => 'b8ac5971875756a83b4ce0552d3de0a3',
                    'messageType' => 'finish',
                    'messageSource' => MessageInitMock::class,
                    'message' => [
                        'data' => 'Tiger',
                    ],
                    'time' => '2026-03-31T06:39:57.909+00:00',
                    'hash' => '019d429e-d1d5-7215-a59c-281b39405b2b',
                    'predecessorHash' => null,
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run5Hash,
                    'stepSource' => NextStepMock::class,
                    'stepHash' => '4b8cb9b50c0221f29b01127972094971',
                    'messageType' => 'finish',
                    'messageSource' => MessageDataMock::class,
                    'message' => [
                        'data' => 'Tiger mit Zwiebeln',
                    ],
                    'time' => '2026-03-31T08:58:26.435+00:00',
                    'hash' => '019d431d-9903-70a6-b1ec-b41ffd26cc68',
                    'predecessorHash' => null,
                ],
            ],
            'flowExceptions' => [],
            'flowResults' => [
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run1Hash,
                    'stepSource' => NextStepMock::class,
                    'stepHash' => '4b8cb9b50c0221f29b01127972094971',
                    'result' => false,
                    'time' => '2026-03-31T06:39:57.000+00:00',
                    'hash' => '019d429e-d1d7-7370-911b-f61113310c27',
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run2Hash,
                    'stepSource' => NextStepMock::class,
                    'stepHash' => '4b8cb9b50c0221f29b01127972094971',
                    'result' => true,
                    'time' => '2026-03-31T06:51:35.000+00:00',
                    'hash' => '019d42a9-7611-7260-9d2a-ee40d6138596',
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run3Hash,
                    'stepSource' => NextStepMock::class,
                    'stepHash' => '4b8cb9b50c0221f29b01127972094971',
                    'result' => true,
                    'time' => '2026-03-31T06:54:42.000+00:00',
                    'hash' => '019d42ac-508b-7209-bb05-0f551526ae65',
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run4Hash,
                    'stepSource' => NextStepMock::class,
                    'stepHash' => '4b8cb9b50c0221f29b01127972094971',
                    'result' => true,
                    'time' => '2026-03-31T07:24:51.000+00:00',
                    'hash' => '019d42c7-ed32-7161-ba2e-25573ce671d7',
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run5Hash,
                    'stepSource' => NextStepMock::class,
                    'stepHash' => '4b8cb9b50c0221f29b01127972094971',
                    'result' => true,
                    'time' => '2026-03-31T08:58:26.000+00:00',
                    'hash' => '019d431d-990f-707f-9062-1ad83987bd4e',
                ],
            ],
            'flowRuns' => [
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run1Hash,
                    'flowType' => 'flow.workflow.v1',
                    'time' => '2026-03-31T06:39:57.000+00:00',
                    'queueId' => '019d429e-d1cc-725e-9bca-35df208ba4ba',
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run2Hash,
                    'flowType' => 'flow.workflow.v1',
                    'time' => '2026-03-31T06:51:35.000+00:00',
                    'queueId' => null,
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run3Hash,
                    'flowType' => 'flow.workflow.v1',
                    'time' => '2026-03-31T06:54:42.000+00:00',
                    'queueId' => null,
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run4Hash,
                    'flowType' => 'flow.workflow.v1',
                    'time' => '2026-03-31T07:24:51.000+00:00',
                    'queueId' => null,
                ],
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $run5Hash,
                    'flowType' => 'flow.workflow.v1',
                    'time' => '2026-03-31T08:58:26.000+00:00',
                    'queueId' => null,
                ],
            ],
        ]);

        $this->assertSame(StatusEnum::OK, $flow->status());
    }

    private function createFlow(?DateTimeImmutable $time = null): Flow
    {
        return Flow::create(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            time: $time,
        );
    }

    /**
     * @param class-string $stepSource
     */
    private function makeMessage(Flow $flow, string $stepSource, ?string $runtimeHash = null): FlowMessage
    {
        return FlowMessage::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $runtimeHash ?? $flow->getRuntimeHash(),
            flowType: $flow->getType(),
            stepSource: $stepSource,
            stepHash: 'step-hash',
            messageTypeEnum: MessageTypeEnum::FINISH,
            messageHash: Source::message(MessageDataSecondMock::class)->messageHash,
            message: new MessageDataMock('test'),
            predecessorHash: null,
        );
    }
}
