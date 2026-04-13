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
use Tests\MockClass\NextStubMock;
use Tests\MockClass\OtherStubMock;
use Tests\MockClass\PostStubMock;
use Tests\MockClass\StubMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowResult;
use Wundii\Flowcrafter\FlowRun;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\StubTiming;

final class FlowRunTimingsTest extends TestCase
{
    private static DateTimeImmutable $base;

    public static function setUpBeforeClass(): void
    {
        self::$base = new DateTimeImmutable('2026-01-01T10:00:00.000+00:00');
    }

    public function testReturnsEmptyArrayWhenNoRuns(): void
    {
        $flow = $this->createFlow();

        $this->assertSame([], $flow->runTimings());
    }

    public function testReturnsOneKeyPerRunEvenWithNoMessages(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $timings = $flow->runTimings();

        $this->assertCount(1, $timings);
        $this->assertArrayHasKey($flow->getRuntimeHash(), $timings);
        $this->assertSame([], $timings[$flow->getRuntimeHash()]);
    }

    public function testStubWithNoActivityInRunIsExcluded(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        // Only StubMock has events — NextStubMock, OtherStubMock, PostStubMock are silent
        $flow->addMessage($this->makeMessage($flow, StubMock::class, new MessageInitMock('test'), 0));

        $timings = $this->indexByStub($timings = $flow->runTimings()[$flow->getRuntimeHash()]);

        $this->assertArrayHasKey(StubMock::class, $timings);
        $this->assertArrayNotHasKey(NextStubMock::class, $timings);
        $this->assertArrayNotHasKey(OtherStubMock::class, $timings);
        $this->assertArrayNotHasKey(PostStubMock::class, $timings);
    }

    /**
     * StubMock: receives MessageInitMock, outputs MessageDataMock.
     *
     * t+000  StubMock ← MessageInitMock          (input — defines START)
     * t+100  NextStubMock ← MessageDataMock       (first downstream appearance — defines END of StubMock)
     *
     * Expected: StubMock start=0, end=100, duration=100
     */
    public function testLinearStubStartIsInputTimeEndIsFirstOutputAppearance(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $flow->addMessage($this->makeMessage($flow, StubMock::class, new MessageInitMock('test'), 0));
        $flow->addMessage($this->makeMessage($flow, NextStubMock::class, new MessageDataMock('out'), 100));

        $timings = $this->indexByStub($flow->runTimings()[$flow->getRuntimeHash()]);

        $this->assertArrayHasKey(StubMock::class, $timings);
        $this->assertTiming($timings[StubMock::class], startOffset: 0, endOffset: 100);
    }

    /**
     * NextStubMock is a bool stub (returnTypes = []) — its END time must come from its FlowResult.
     *
     * t+100  NextStubMock ← MessageDataMock       (input — defines START)
     * t+200  FlowResult(NextStubMock)              (result — defines END)
     *
     * Expected: NextStubMock start=100, end=200, duration=100
     */
    public function testBoolStubEndTimeIsFlowResultTime(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $flow->addMessage($this->makeMessage($flow, StubMock::class, new MessageInitMock('test'), 0));
        $flow->addMessage($this->makeMessage($flow, NextStubMock::class, new MessageDataMock('out'), 100));
        $flow->addResult($this->makeResult($flow, NextStubMock::class, 200));

        $timings = $this->indexByStub($flow->runTimings()[$flow->getRuntimeHash()]);

        $this->assertArrayHasKey(NextStubMock::class, $timings);
        $this->assertTiming($timings[NextStubMock::class], startOffset: 100, endOffset: 200);
    }

    /**
     * PostStubMock mirrors HomeEnergySystemFlow's ResultProcessStub: it requires TWO inputs.
     * Its START must be the LATER of the two input arrivals — it cannot begin until both are present.
     *
     * t+000  StubMock    ← MessageInitMock
     * t+100  PostStubMock ← MessageDataMock        (first input, from StubMock output)
     * t+300  PostStubMock ← MessageDataSecondMock  (second input, from OtherStubMock output)
     * t+400  PostStubMock ← MessageReturnMock      (own output — defines END)
     *
     * Expected: PostStubMock start=300 (max of 100 and 300), end=400, duration=100
     */
    public function testCollectorStubStartIsMaxOfAllInputArrivals(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $flow->addMessage($this->makeMessage($flow, StubMock::class, new MessageInitMock('test'), 0));
        $flow->addMessage($this->makeMessage($flow, PostStubMock::class, new MessageDataMock('a'), 100));
        $flow->addMessage($this->makeMessage($flow, PostStubMock::class, new MessageDataSecondMock('b', new MessageSubDataMock('sub')), 300));
        $flow->addMessage($this->makeMessage($flow, PostStubMock::class, new MessageReturnMock('result'), 400));

        $timings = $this->indexByStub($flow->runTimings()[$flow->getRuntimeHash()]);

        $this->assertArrayHasKey(PostStubMock::class, $timings);
        $this->assertTiming($timings[PostStubMock::class], startOffset: 300, endOffset: 400);
    }

    /**
     * Full WorkflowMock run — all four stubs, controlled timestamps.
     *
     * t+000  StubMock    ← MessageInitMock
     * t+100  NextStubMock  ← MessageDataMock       (StubMock output landed at all fan-out targets)
     * t+100  OtherStubMock ← MessageDataMock
     * t+100  PostStubMock  ← MessageDataMock       (partial input — PostStubMock still waits)
     * t+200  FlowResult(NextStubMock, true)
     * t+300  PostStubMock  ← MessageDataSecondMock (OtherStubMock output — PostStubMock now runnable)
     * t+400  PostStubMock  ← MessageReturnMock     (PostStubMock own output)
     *
     * Expected:
     *   StubMock     start=0,   end=100  (firstAppearance[MessageDataMock]=100),   duration=100
     *   NextStubMock start=100, end=200  (bool: result at 200),                    duration=100
     *   OtherStubMock start=100, end=300 (firstAppearance[MessageDataSecondMock]=300), duration=200
     *   PostStubMock  start=300, end=400 (max inputs=300, firstAppearance[MessageReturnMock]=400), duration=100
     */
    public function testFullWorkflowTimings(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $flow->addMessage($this->makeMessage($flow, StubMock::class, new MessageInitMock('test'), 0));
        $flow->addMessage($this->makeMessage($flow, NextStubMock::class, new MessageDataMock('out'), 100));
        $flow->addMessage($this->makeMessage($flow, OtherStubMock::class, new MessageDataMock('out'), 100));
        $flow->addMessage($this->makeMessage($flow, PostStubMock::class, new MessageDataMock('out'), 100));
        $flow->addResult($this->makeResult($flow, NextStubMock::class, 200));
        $flow->addMessage($this->makeMessage($flow, PostStubMock::class, new MessageDataSecondMock('b', new MessageSubDataMock('sub')), 300));
        $flow->addMessage($this->makeMessage($flow, PostStubMock::class, new MessageReturnMock('done'), 400));

        $timings = $this->indexByStub($flow->runTimings()[$flow->getRuntimeHash()]);

        $this->assertCount(4, $timings);
        $this->assertTiming($timings[StubMock::class], startOffset: 0, endOffset: 100);
        $this->assertTiming($timings[NextStubMock::class], startOffset: 100, endOffset: 200);
        $this->assertTiming($timings[OtherStubMock::class], startOffset: 100, endOffset: 300);
        $this->assertTiming($timings[PostStubMock::class], startOffset: 300, endOffset: 400);
    }

    /**
     * Two independent runs must produce separate timing entries keyed by their own runtimeHash.
     * Run 1 is faster (StubMock ends at 100ms); run 2 is slower (StubMock ends at 200ms).
     *
     * addRun() always reuses the same runtimeHash from the constructor, so two distinct runs
     * require constructing the Flow with explicit FlowRun objects and pre-built message lists.
     */
    public function testMultipleRunsProduceIndependentTimings(): void
    {
        $flowHash = '019d429e-d1d1-7029-b178-4372d2f8dedc';
        $run1Hash = '019d429e-d1d1-7029-b178-4372d35ed9cc';
        $run2Hash = '019d42a9-75ff-7261-8ba6-4e74bd8b5dd7';

        $makeMsg = fn (string $runtimeHash, string $stubSource, object $message, int $offsetMs): FlowMessage => FlowMessage::create(
            flowHash: $flowHash,
            flowRuntimeHash: $runtimeHash,
            stubSource: $stubSource,
            stubHash: 'stub-hash',
            messageTypeEnum: MessageTypeEnum::FINISH,
            messageHash: 'msg-hash',
            message: $message,
            predecessorHash: null,
            time: $this->t($offsetMs),
        );
        $makeRes = fn (string $runtimeHash, string $stubSource, int $offsetMs): FlowResult => FlowResult::create(
            flowHash: $flowHash,
            flowRuntimeHash: $runtimeHash,
            stubSource: $stubSource,
            stubHash: 'stub-hash',
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
                // run 1: StubMock at t+0, NextStubMock input at t+100
                $makeMsg($run1Hash, StubMock::class, new MessageInitMock('r1'), 0),
                $makeMsg($run1Hash, NextStubMock::class, new MessageDataMock('r1'), 100),
                // run 2: StubMock at t+0, NextStubMock input at t+200
                $makeMsg($run2Hash, StubMock::class, new MessageInitMock('r2'), 0),
                $makeMsg($run2Hash, NextStubMock::class, new MessageDataMock('r2'), 200),
            ],
            flowRuns: [
                FlowRun::create($flowHash, $run1Hash, 'flow.workflow.v1'),
                FlowRun::create($flowHash, $run2Hash, 'flow.workflow.v1'),
            ],
            flowResults: [
                $makeRes($run1Hash, NextStubMock::class, 200),
                $makeRes($run2Hash, NextStubMock::class, 400),
            ],
        );

        $allTimings = $flow->runTimings();

        $this->assertCount(2, $allTimings);

        $run1Timings = $this->indexByStub($allTimings[$run1Hash]);
        $this->assertTiming($run1Timings[StubMock::class], startOffset: 0, endOffset: 100);
        $this->assertTiming($run1Timings[NextStubMock::class], startOffset: 100, endOffset: 200);

        $run2Timings = $this->indexByStub($allTimings[$run2Hash]);
        $this->assertTiming($run2Timings[StubMock::class], startOffset: 0, endOffset: 200);
        $this->assertTiming($run2Timings[NextStubMock::class], startOffset: 200, endOffset: 400);
    }

    public function testBoolStubWithNoResultHasDurationZero(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        $flow->addMessage($this->makeMessage($flow, NextStubMock::class, new MessageDataMock('in'), 100));
        // intentionally no FlowResult added

        $timings = $this->indexByStub($flow->runTimings()[$flow->getRuntimeHash()]);

        $this->assertArrayHasKey(NextStubMock::class, $timings);
        $this->assertSame(0, $timings[NextStubMock::class]->getDuration());
    }

    public function testStubWithNoDownstreamOutputYetHasDurationZero(): void
    {
        $flow = $this->createFlow();
        $flow->addRun();

        // StubMock has its input but its output (MessageDataMock) has not yet appeared anywhere
        $flow->addMessage($this->makeMessage($flow, StubMock::class, new MessageInitMock('test'), 0));

        $timings = $this->indexByStub($flow->runTimings()[$flow->getRuntimeHash()]);

        $this->assertArrayHasKey(StubMock::class, $timings);
        $this->assertSame(0, $timings[StubMock::class]->getDuration());
    }

    // ── helpers ──────────────────────────────────────────────────────────────

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
        string $stubSource,
        object $message,
        int $offsetMs,
        ?string $runtimeHash = null,
    ): FlowMessage {
        return FlowMessage::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $runtimeHash ?? $flow->getRuntimeHash(),
            stubSource: $stubSource,
            stubHash: 'stub-hash',
            messageTypeEnum: MessageTypeEnum::FINISH,
            messageHash: 'msg-hash',
            message: $message,
            predecessorHash: null,
            time: $this->t($offsetMs),
        );
    }

    private function makeResult(
        Flow $flow,
        string $stubSource,
        int $offsetMs,
        bool $result = true,
        ?string $runtimeHash = null,
    ): FlowResult {
        return FlowResult::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $runtimeHash ?? $flow->getRuntimeHash(),
            stubSource: $stubSource,
            stubHash: 'stub-hash',
            result: $result,
            time: $this->t($offsetMs),
        );
    }

    /**
     * @param StubTiming[] $timings
     * @return array<string, StubTiming>
     */
    private function indexByStub(array $timings): array
    {
        $indexed = [];
        foreach ($timings as $timing) {
            $indexed[$timing->getStubSource()] = $timing;
        }

        return $indexed;
    }

    private function assertTiming(StubTiming $stubTiming, int $startOffset, int $endOffset): void
    {
        $baseMs = $this->ms(self::$base);
        $this->assertSame($startOffset, $this->ms($stubTiming->getStarted()) - $baseMs, 'started offset');
        $this->assertSame($endOffset, $this->ms($stubTiming->getEnded()) - $baseMs, 'ended offset');
        $this->assertSame(max(0, $endOffset - $startOffset), $stubTiming->getDuration(), 'duration');
    }
}
