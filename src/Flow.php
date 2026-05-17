<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class Flow implements JsonSerializable
{
    private string $flowRuntimeHash;

    private bool $flowReadOnly;

    /**
     * @var class-string[]
     */
    private array $includeSteps = [];

    /**
     * @param class-string<FlowInterface> $flowSource
     * @param FlowMessage[] $flowMessages
     * @param FlowException[] $flowExceptions
     * @param FlowRun[] $flowRuns
     * @param FlowResult[] $flowResults
     * @param FlowRetry[] $flowRetries
     * @param string[] $flowReadOnlyReasons
     */
    public function __construct(
        private readonly string $flowType,
        private readonly string $flowSource,
        private readonly FlowSchema $flowSchema,
        private readonly string $flowSchemaHash,
        private readonly DateTimeImmutable $time,
        private readonly string $flowHash,
        private readonly ?string $flowSubject = null,
        private array $flowMessages = [],
        private array $flowExceptions = [],
        private array $flowRuns = [],
        private array $flowResults = [],
        private array $flowRetries = [],
        private readonly array $flowReadOnlyReasons = [],
    ) {
        $this->flowReadOnly = $flowReadOnlyReasons !== [];
        if (!$this->flowReadOnly) {
            Assert::classString(
                $flowSource,
                FlowInterface::class,
                sprintf('Flow source class "%s" does not implement FlowInterface.', $flowSource),
            );
        }

        if (!Assert::isHash($flowHash)) {
            throw new InvalidArgumentException(sprintf(
                'Hash "%s" is not a valid hash.',
                $flowHash,
            ));
        }

        if (!preg_match('/^flow\..+\.v\d+$/', $flowType)) {
            throw new InvalidArgumentException(sprintf(
                'Flow type "%s" must start with "flow." and end with ".v" followed by a number (e.g. "flow.example.v1").',
                $flowType,
            ));
        }

        if ($flowType !== $this->flowSchema->type()) {
            throw new InvalidArgumentException(sprintf(
                'Flow type "%s" does not match the schema type "%s".',
                $flowType,
                $this->flowSchema->type(),
            ));
        }

        $this->flowRuntimeHash = Uuid::uuid7($time)->toString();
    }

    /**
     * @param class-string<FlowInterface> $flowSource
     */
    public static function create(
        string $flowType,
        string $flowSource,
        ?string $flowSchemaHash = null,
        ?string $flowSubject = null,
        ?string $flowHash = null,
        ?DateTimeImmutable $time = null,
    ): self {
        $flowSchema = FlowSchema::create($flowSource);
        $flowSchemaHash = $flowSchemaHash ?? $flowSchema->getHash();
        $time = $time ?? new DateTimeImmutable();

        return new self(
            flowType: $flowType,
            flowSource: $flowSource,
            flowSchema: $flowSchema,
            flowSchemaHash: $flowSchemaHash,
            time: $time,
            flowHash: $flowHash ?? Uuid::uuid7($time)->toString(),
            flowSubject: $flowSubject,
        );
    }

    /**
     * @return class-string<FlowInterface>
     */
    public function getSource(): string
    {
        return $this->flowSource;
    }

    public function getSubject(): ?string
    {
        return $this->flowSubject;
    }

    public function getType(): string
    {
        return $this->flowType;
    }

    public function getSchema(): FlowSchema
    {
        return $this->flowSchema;
    }

    public function getSchemaHash(): string
    {
        return $this->flowSchemaHash;
    }

    public function getHash(): string
    {
        return $this->flowHash;
    }

    public function getRuntimeHash(): string
    {
        return $this->flowRuntimeHash;
    }

    public function getTime(): DateTimeImmutable
    {
        return $this->time;
    }

    /**
     * @return FlowMessage[]
     */
    public function getFlowMessages(): array
    {
        return $this->flowMessages;
    }

    public function addMessage(FlowMessage $flowMessage): void
    {
        $this->flowMessages[] = $flowMessage;
    }

    /**
     * @return FlowException[]
     */
    public function getFlowExceptions(): array
    {
        return $this->flowExceptions;
    }

    public function addException(FlowException $flowException): void
    {
        $this->flowExceptions[] = $flowException;
    }

    /**
     * @return FlowResult[]
     */
    public function getFlowResults(): array
    {
        return $this->flowResults;
    }

    public function addResult(FlowResult $flowResult): void
    {
        $this->flowResults[] = $flowResult;
    }

    /**
     * @return FlowRetry[]
     */
    public function getFlowRetries(): array
    {
        return $this->flowRetries;
    }

    public function addFlowRetry(FlowRetry $flowRetry): void
    {
        $this->flowRetries[] = $flowRetry;
    }

    /**
     * @return FlowRun[]
     */
    public function runs(): array
    {
        $this->runStepTimings();

        return array_values($this->flowRuns);
    }

    public function addRun(?string $queueId = null, ?DateTimeInterface $datetime = null): void
    {
        $this->flowRuns[$this->getRuntimeHash()] = FlowRun::create(
            $this->getHash(),
            $this->getRuntimeHash(),
            $this->getType(),
            $datetime,
            $queueId,
        );
    }

    /**
     * @param class-string<MessageInterface> $message
     */
    public function hasMessage(string $message): bool
    {
        Assert::classString($message, MessageInterface::class);

        foreach ($this->flowMessages as $flowMessage) {
            if ($flowMessage->getMessage() instanceof $message) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string<StepInterface> $stepSource
     * @return FlowMessage[]
     */
    public function executableMessages(string $stepSource): array
    {
        Assert::classString($stepSource, StepInterface::class);

        $flowMessages = array_filter(
            $this->flowMessages,
            static fn (FlowMessage $flowMessage): bool => $flowMessage->getStepSource() === $stepSource
                && $flowMessage->getMessageType() === MessageTypeEnum::WAIT,
        );

        $step = $this->flowSchema->stepBySource($stepSource);
        $stepMessageClasses = $step->getMessages();
        $flowMessageClasses = array_map(
            static fn (FlowMessage $flowMessage): string => $flowMessage->getMessageSource(),
            $flowMessages,
        );

        sort($stepMessageClasses);
        sort($flowMessageClasses);

        if ($stepMessageClasses !== $flowMessageClasses) {
            return [];
        }

        foreach ($flowMessages as $flowMessage) {
            $flowMessage->setProcess();
        }

        return $flowMessages;
    }

    public function isExecutable(): bool
    {
        if ($this->flowReadOnly) {
            return false;
        }

        return $this->flowSchemaHash === $this->flowSchema->getHash();
    }

    public function isReadOnly(): bool
    {
        return $this->flowReadOnly;
    }

    /**
     * @return string[]
     */
    public function getReadOnlyReasons(): array
    {
        return $this->flowReadOnlyReasons;
    }

    /**
     * @param class-string[] $includeSteps
     */
    public function setIncludeSteps(array $includeSteps): void
    {
        $this->includeSteps = $includeSteps;
    }

    public function status(): StatusEnum
    {
        if ($this->flowRuns === []) {
            return StatusEnum::IN_PROGRESS;
        }

        $flowRun = end($this->flowRuns);
        $lastRuntimeHash = $flowRun->getFlowRuntimeHash();
        $pendingLeafs = $this->resolvePendingLeafs($lastRuntimeHash);

        foreach ($this->flowExceptions as $flowException) {
            if ($flowException->getFlowRuntimeHash() === $lastRuntimeHash) {
                return StatusEnum::FAILED;
            }
        }

        if ($pendingLeafs !== []) {
            $now = new DateTimeImmutable('now', $flowRun->getTime()->getTimezone());
            $exceeded = DateTimeImmutable::createFromInterface($flowRun->getTime())->modify('+1 hour') < $now;
            return $exceeded ? StatusEnum::IN_PROGRESS_EXCEEDED : StatusEnum::IN_PROGRESS;
        }

        foreach ($this->flowResults as $flowResult) {
            if ($flowResult->getFlowRuntimeHash() === $lastRuntimeHash && !$flowResult->getResult()) {
                return StatusEnum::WARNING;
            }
        }

        return StatusEnum::OK;
    }

    public function runStepTimings(): void
    {
        foreach ($this->flowRuns as $flowRun) {
            $runtimeHash = $flowRun->getFlowRuntimeHash();

            $runMessages = array_values(array_filter(
                $this->flowMessages,
                fn (FlowMessage $flowMessage): bool => $flowMessage->getFlowRuntimeHash() === $runtimeHash,
            ));
            $runExceptions = array_values(array_filter(
                $this->flowExceptions,
                fn (FlowException $flowException): bool => $flowException->getFlowRuntimeHash() === $runtimeHash,
            ));
            $runResults = array_values(array_filter(
                $this->flowResults,
                fn (FlowResult $flowResult): bool => $flowResult->getFlowRuntimeHash() === $runtimeHash,
            ));

            // firstAppearance[$messageSource] = earliest ms timestamp for that messageSource
            $firstAppearance = [];
            foreach ($runMessages as $runMessage) {
                $ms = $this->toMs($runMessage->getTime());
                $src = $runMessage->getMessageSource();
                if (!isset($firstAppearance[$src]) || $ms < $firstAppearance[$src]) {
                    $firstAppearance[$src] = $ms;
                }
            }

            $timings = [];
            foreach ($this->flowSchema->steps() as $step) {
                $stepSource = $step->getSource();

                $stepMsgs = array_values(array_filter($runMessages, fn (FlowMessage $flowMessage): bool => $flowMessage->getStepSource() === $stepSource));
                $stepExcs = array_values(array_filter($runExceptions, fn (FlowException $flowException): bool => $flowException->getStepSource() === $stepSource));
                $stepRess = array_values(array_filter($runResults, fn (FlowResult $flowResult): bool => $flowResult->getStepSource() === $stepSource));

                if ($stepMsgs === [] && $stepExcs === [] && $stepRess === []) {
                    continue;
                }

                // START = when the last required input arrived (step can only run once all inputs are present)
                $inputMsgs = array_values(array_filter(
                    $stepMsgs,
                    fn (FlowMessage $flowMessage): bool => in_array($flowMessage->getMessageSource(), $step->getMessages(), true),
                ));

                if ($inputMsgs !== []) {
                    $startMs = max(array_map(fn (FlowMessage $flowMessage): int => $this->toMs($flowMessage->getTime()), $inputMsgs));
                } else {
                    $allMs = array_merge(
                        array_map(fn (FlowMessage $flowMessage): int => $this->toMs($flowMessage->getTime()), $stepMsgs),
                        array_map(fn (FlowException $flowException): int => $this->toMs($flowException->getTime()), $stepExcs),
                        array_map(fn (FlowResult $flowResult): int => $this->toMs($flowResult->getTime()), $stepRess),
                    );
                    if ($allMs === []) {
                        continue;
                    }

                    $startMs = min($allMs);
                }

                // END = when the earliest output message first appeared at another step (or result for bool steps)
                if ($step->getReturnTypes() !== []) {
                    $outputMs = array_values(array_filter(
                        array_map(fn (string $rt): ?int => $firstAppearance[$rt] ?? null, $step->getReturnTypes()),
                        fn (?int $t): bool => $t !== null,
                    ));
                    $endMs = $outputMs !== [] ? min($outputMs) : $startMs;
                } else {
                    $endMs = $stepRess !== []
                        ? max(array_map(fn (FlowResult $flowResult): int => $this->toMs($flowResult->getTime()), $stepRess))
                        : $startMs;
                }

                $timings[] = new StepTiming(
                    $stepSource,
                    $this->fromMs($startMs),
                    $this->fromMs($endMs),
                );
            }

            $flowRun->setStepTimings($timings);
        }
    }

    /**
     * @return array<string, null|bool|string|array<FlowMessage|FlowException|FlowResult|FlowRun|FlowRetry|StepTiming[]|string>|FlowSchema>
     */
    public function jsonSerialize(): array
    {
        return [
            'flowSource' => $this->flowSource,
            'flowSubject' => $this->flowSubject,
            'flowType' => $this->flowType,
            'flowSchema' => $this->flowSchema,
            'flowSchemaHash' => $this->flowSchemaHash,
            'flowHash' => $this->flowHash,
            'flowMessages' => $this->flowMessages,
            'flowExceptions' => $this->flowExceptions,
            'flowResults' => $this->flowResults,
            'flowRetries' => $this->flowRetries,
            'flowRuns' => $this->runs(),
            'flowStatus' => $this->status()->name,
            'isExecutable' => $this->isExecutable(),
            'isReadOnly' => $this->flowReadOnly,
            'readOnlyReasons' => $this->flowReadOnlyReasons,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }

    private function toMs(DateTimeInterface $time): int
    {
        return $time->getTimestamp() * 1000 + (int) $time->format('v');
    }

    private function fromMs(int $ms): DateTimeImmutable
    {
        $seconds = intdiv($ms, 1000);
        $millis = $ms % 1000;

        return (new DateTimeImmutable('@' . $seconds))->modify(sprintf('+%d milliseconds', $millis));
    }

    /**
     * Returns the leaf step class strings that have not yet been reached.
     * First checks the last run, then falls back to previous registered runs
     * to support targeted partial re-runs where some leafs were handled earlier.
     *
     * @return string[]
     */
    private function resolvePendingLeafs(string $lastRuntimeHash): array
    {
        $leafSources = array_map(
            static fn (Step $step): string => $step->getSource(),
            $this->flowSchema->getLeafSteps(),
        );

        if ($this->includeSteps !== []) {
            $lastRunSources = array_unique(array_map(
                static fn (FlowMessage $flowMessage): string => $flowMessage->getStepSource(),
                array_filter(
                    $this->flowMessages,
                    fn (FlowMessage $flowMessage): bool => $flowMessage->getFlowRuntimeHash() === $lastRuntimeHash,
                ),
            ));
            $leafSources = array_values(array_intersect($leafSources, $lastRunSources));
        }

        $relevantHashes = [$lastRuntimeHash];
        if (count($this->flowRuns) > 1) {
            $previousHashes = array_map(
                static fn (FlowRun $flowRun): string => $flowRun->getFlowRuntimeHash(),
                array_slice(array_values($this->flowRuns), 0, -1),
            );
            $relevantHashes = array_merge($relevantHashes, $previousHashes);
        }

        foreach ($this->flowMessages as $flowMessage) {
            if (!in_array($flowMessage->getFlowRuntimeHash(), $relevantHashes, true)) {
                continue;
            }

            $key = array_search($flowMessage->getStepSource(), $leafSources, true);
            if ($key !== false) {
                unset($leafSources[$key]);
            }
        }

        return array_values($leafSources);
    }
}
