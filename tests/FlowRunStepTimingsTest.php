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
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowResult;
use Wundii\Flowcrafter\FlowRun;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\StepTiming;

final class FlowRunStepTimingsTest extends TestCase
{
    private static DateTimeImmutable $base;

    public static function setUpBeforeClass(): void
    {
        self::$base = new DateTimeImmutable('2026-01-01T10:00:00.000+00:00');
    }

    public function testReturnsEmptyArrayWhenNoRuns(): void
    {
        $flow = $this->createFlow();

        $this->assertSame([], $flow->runs());
    }

    public function testReturnsOneKeyPerRunEvenWithNoMessages(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $runs = $flow->runs();

        $this->assertCount(1, $runs);
        $this->assertSame($flow->getRuntimeHash(), $runs[0]->getFlowRuntimeHash());
        $this->assertSame([], $runs[0]->getStepTimings());
    }

    public function testStepWithNoActivityInRunIsExcluded(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        // Only StepMock has events — NextStepMock, OtherStepMock, PostStepMock are silent
        $flow->addMessage($this->makeMessage($flow, StepMock::class, new MessageInitMock('test'), 0));

        $timings = $this->indexByStep($this->getTimingsForRun($flow, $flow->getRuntimeHash()));

        $this->assertArrayHasKey(StepMock::class, $timings);
        $this->assertArrayNotHasKey(NextStepMock::class, $timings);
        $this->assertArrayNotHasKey(OtherStepMock::class, $timings);
        $this->assertArrayNotHasKey(PostStepMock::class, $timings);
    }

    /**
     * StepMock: receives MessageInitMock, outputs MessageDataMock.
     *
     * t+000  StepMock ← MessageInitMock          (input — defines START)
     * t+100  NextStepMock ← MessageDataMock       (first downstream appearance — defines END of StepMock)
     *
     * Expected: StepMock start=0, end=100, duration=100
     */
    public function testLinearStepStartIsInputTimeEndIsFirstOutputAppearance(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $flow->addMessage($this->makeMessage($flow, StepMock::class, new MessageInitMock('test'), 0));
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class, new MessageDataMock('out'), 100));

        $timings = $this->indexByStep($this->getTimingsForRun($flow, $flow->getRuntimeHash()));

        $this->assertArrayHasKey(StepMock::class, $timings);
        $this->assertTiming($timings[StepMock::class], startOffset: 0, endOffset: 100);
    }

    /**
     * NextStepMock is a bool step (returnTypes = []) — its END time must come from its FlowResult.
     *
     * t+100  NextStepMock ← MessageDataMock       (input — defines START)
     * t+200  FlowResult(NextStepMock)              (result — defines END)
     *
     * Expected: NextStepMock start=100, end=200, duration=100
     */
    public function testBoolStepEndTimeIsFlowResultTime(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $flow->addMessage($this->makeMessage($flow, StepMock::class, new MessageInitMock('test'), 0));
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class, new MessageDataMock('out'), 100));
        $flow->addResult($this->makeResult($flow, NextStepMock::class, 200));

        $timings = $this->indexByStep($this->getTimingsForRun($flow, $flow->getRuntimeHash()));

        $this->assertArrayHasKey(NextStepMock::class, $timings);
        $this->assertTiming($timings[NextStepMock::class], startOffset: 100, endOffset: 200);
    }

    /**
     * PostStepMock mirrors HomeEnergySystemFlow's ResultProcessStep: it requires TWO inputs.
     * Its START must be the LATER of the two input arrivals — it cannot begin until both are present.
     *
     * t+000  StepMock    ← MessageInitMock
     * t+100  PostStepMock ← MessageDataMock        (first input, from StepMock output)
     * t+300  PostStepMock ← MessageDataSecondMock  (second input, from OtherStepMock output)
     * t+400  PostStepMock ← MessageReturnMock      (own output — defines END)
     *
     * Expected: PostStepMock start=300 (max of 100 and 300), end=400, duration=100
     */
    public function testCollectorStepStartIsMaxOfAllInputArrivals(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $flow->addMessage($this->makeMessage($flow, StepMock::class, new MessageInitMock('test'), 0));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class, new MessageDataMock('a'), 100));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class, new MessageDataSecondMock('b', new MessageSubDataMock('sub')), 300));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class, new MessageReturnMock('result'), 400));

        $timings = $this->indexByStep($this->getTimingsForRun($flow, $flow->getRuntimeHash()));

        $this->assertArrayHasKey(PostStepMock::class, $timings);
        $this->assertTiming($timings[PostStepMock::class], startOffset: 300, endOffset: 400);
    }

    /**
     * Full WorkflowMock run — all four steps, controlled timestamps.
     *
     * t+000  StepMock    ← MessageInitMock
     * t+100  NextStepMock  ← MessageDataMock       (StepMock output landed at all fan-out targets)
     * t+100  OtherStepMock ← MessageDataMock
     * t+100  PostStepMock  ← MessageDataMock       (partial input — PostStepMock still waits)
     * t+200  FlowResult(NextStepMock, true)
     * t+300  PostStepMock  ← MessageDataSecondMock (OtherStepMock output — PostStepMock now runnable)
     * t+400  PostStepMock  ← MessageReturnMock     (PostStepMock own output)
     *
     * Expected:
     *   StepMock     start=0,   end=100  (firstAppearance[MessageDataMock]=100),   duration=100
     *   NextStepMock start=100, end=200  (bool: result at 200),                    duration=100
     *   OtherStepMock start=100, end=300 (firstAppearance[MessageDataSecondMock]=300), duration=200
     *   PostStepMock  start=300, end=400 (max inputs=300, firstAppearance[MessageReturnMock]=400), duration=100
     */
    public function testFullWorkflowTimings(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $flow->addMessage($this->makeMessage($flow, StepMock::class, new MessageInitMock('test'), 0));
        $flow->addMessage($this->makeMessage($flow, NextStepMock::class, new MessageDataMock('out'), 100));
        $flow->addMessage($this->makeMessage($flow, OtherStepMock::class, new MessageDataMock('out'), 100));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class, new MessageDataMock('out'), 100));
        $flow->addResult($this->makeResult($flow, NextStepMock::class, 200));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class, new MessageDataSecondMock('b', new MessageSubDataMock('sub')), 300));
        $flow->addMessage($this->makeMessage($flow, PostStepMock::class, new MessageReturnMock('done'), 400));

        $timings = $this->indexByStep($this->getTimingsForRun($flow, $flow->getRuntimeHash()));

        $this->assertCount(4, $timings);
        $this->assertTiming($timings[StepMock::class], startOffset: 0, endOffset: 100);
        $this->assertTiming($timings[NextStepMock::class], startOffset: 100, endOffset: 200);
        $this->assertTiming($timings[OtherStepMock::class], startOffset: 100, endOffset: 300);
        $this->assertTiming($timings[PostStepMock::class], startOffset: 300, endOffset: 400);
    }

    /**
     * Two independent runs must produce separate timing entries keyed by their own runtimeHash.
     * Run 1 is faster (StepMock ends at 100ms); run 2 is slower (StepMock ends at 200ms).
     *
     * addRun() always reuses the same runtimeHash from the constructor, so two distinct runs
     * require constructing the Flow with explicit FlowRun objects and pre-built message lists.
     */
    public function testMultipleRunsProduceIndependentTimings(): void
    {
        $flowHash = '019d429e-d1d1-7029-b178-4372d2f8dedc';
        $run1Hash = '019d429e-d1d1-7029-b178-4372d35ed9cc';
        $run2Hash = '019d42a9-75ff-7261-8ba6-4e74bd8b5dd7';

        $makeMsg = fn (string $runtimeHash, string $stepSource, object $message, int $offsetMs): FlowMessage => FlowMessage::create(
            flowHash: $flowHash,
            flowRuntimeHash: $runtimeHash,
            stepSource: $stepSource,
            stepHash: 'step-hash',
            messageTypeEnum: MessageTypeEnum::FINISH,
            messageHash: 'msg-hash',
            message: $message,
            predecessorHash: null,
            time: $this->t($offsetMs),
        );
        $makeRes = fn (string $runtimeHash, string $stepSource, int $offsetMs): FlowResult => FlowResult::create(
            flowHash: $flowHash,
            flowRuntimeHash: $runtimeHash,
            stepSource: $stepSource,
            stepHash: 'step-hash',
            result: true,
            time: $this->t($offsetMs),
        );

        $flow = new Flow(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSchema: FlowSchema::create(WorkflowMock::class),
            flowSchemaHash: FlowSchema::create(WorkflowMock::class)->getHash(),
            time: new DateTimeImmutable('2026-01-01T00:00:00.000+00:00'),
            flowHash: $flowHash,
            flowMessages: [
                // run 1: StepMock at t+0, NextStepMock input at t+100
                $makeMsg($run1Hash, StepMock::class, new MessageInitMock('r1'), 0),
                $makeMsg($run1Hash, NextStepMock::class, new MessageDataMock('r1'), 100),
                // run 2: StepMock at t+0, NextStepMock input at t+200
                $makeMsg($run2Hash, StepMock::class, new MessageInitMock('r2'), 0),
                $makeMsg($run2Hash, NextStepMock::class, new MessageDataMock('r2'), 200),
            ],
            flowRuns: [
                FlowRun::create($flowHash, $run1Hash, 'flow.workflow.v1'),
                FlowRun::create($flowHash, $run2Hash, 'flow.workflow.v1'),
            ],
            flowResults: [
                $makeRes($run1Hash, NextStepMock::class, 200),
                $makeRes($run2Hash, NextStepMock::class, 400),
            ],
        );

        $runs = $flow->runs();

        $this->assertCount(2, $runs);

        $run1Timings = $this->indexByStep($this->getTimingsForRun($flow, $run1Hash));
        $this->assertTiming($run1Timings[StepMock::class], startOffset: 0, endOffset: 100);
        $this->assertTiming($run1Timings[NextStepMock::class], startOffset: 100, endOffset: 200);

        $run2Timings = $this->indexByStep($this->getTimingsForRun($flow, $run2Hash));
        $this->assertTiming($run2Timings[StepMock::class], startOffset: 0, endOffset: 200);
        $this->assertTiming($run2Timings[NextStepMock::class], startOffset: 200, endOffset: 400);
    }

    public function testBoolStepWithNoResultHasDurationZero(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $flow->addMessage($this->makeMessage($flow, NextStepMock::class, new MessageDataMock('in'), 100));
        // intentionally no FlowResult added

        $timings = $this->indexByStep($this->getTimingsForRun($flow, $flow->getRuntimeHash()));

        $this->assertArrayHasKey(NextStepMock::class, $timings);
        $this->assertSame(0, $timings[NextStepMock::class]->getDuration());
    }

    public function testStepWithNoDownstreamOutputYetHasDurationZero(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        // StepMock has its input but its output (MessageDataMock) has not yet appeared anywhere
        $flow->addMessage($this->makeMessage($flow, StepMock::class, new MessageInitMock('test'), 0));

        $timings = $this->indexByStep($this->getTimingsForRun($flow, $flow->getRuntimeHash()));

        $this->assertArrayHasKey(StepMock::class, $timings);
        $this->assertSame(0, $timings[StepMock::class]->getDuration());
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * @return StepTiming[]
     */
    private function getTimingsForRun(Flow $flow, string $runtimeHash): array
    {
        foreach ($flow->runs() as $flowRun) {
            if ($flowRun->getFlowRuntimeHash() === $runtimeHash) {
                return $flowRun->getStepTimings();
            }
        }

        return [];
    }

    private function t(int $offsetMs): DateTimeImmutable
    {
        return self::$base->modify(sprintf('+%d milliseconds', $offsetMs));
    }

    private function ms(DateTimeImmutable $dt): int
    {
        return $dt->getTimestamp() * 1000 + (int) $dt->format('v');
    }

    private function createFlow(): Flow
    {
        return Flow::create(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
    }

    private function makeMessage(
        Flow $flow,
        string $stepSource,
        object $message,
        int $offsetMs,
        ?string $runtimeHash = null,
    ): FlowMessage {
        return FlowMessage::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $runtimeHash ?? $flow->getRuntimeHash(),
            stepSource: $stepSource,
            stepHash: 'step-hash',
            messageTypeEnum: MessageTypeEnum::FINISH,
            messageHash: 'msg-hash',
            message: $message,
            predecessorHash: null,
            time: $this->t($offsetMs),
        );
    }

    private function makeResult(
        Flow $flow,
        string $stepSource,
        int $offsetMs,
        bool $result = true,
        ?string $runtimeHash = null,
    ): FlowResult {
        return FlowResult::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $runtimeHash ?? $flow->getRuntimeHash(),
            stepSource: $stepSource,
            stepHash: 'step-hash',
            result: $result,
            time: $this->t($offsetMs),
        );
    }

    /**
     * @param StepTiming[] $timings
     * @return array<string, StepTiming>
     */
    private function indexByStep(array $timings): array
    {
        $indexed = [];
        foreach ($timings as $timing) {
            $indexed[$timing->getStepSource()] = $timing;
        }

        return $indexed;
    }

    private function assertTiming(StepTiming $stepTiming, int $startOffset, int $endOffset): void
    {
        $baseMs = $this->ms(self::$base);
        $this->assertSame($startOffset, $this->ms($stepTiming->getStarted()) - $baseMs, 'started offset');
        $this->assertSame($endOffset, $this->ms($stepTiming->getEnded()) - $baseMs, 'ended offset');
        $this->assertSame(max(0, $endOffset - $startOffset), $stepTiming->getDuration(), 'duration');
    }
}
