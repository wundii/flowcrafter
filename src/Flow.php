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
use Wundii\Flowcrafter\Interface\StubInterface;

class Flow implements JsonSerializable
{
    private string $flowRuntimeHash;

    /**
     * @param class-string<FlowInterface> $flowSource
     * @param FlowMessage[] $flowMessages
     * @param FlowException[] $flowExceptions
     * @param FlowRun[] $flowRuns
     * @param FlowResult[] $flowResults
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
        private bool $flowReadOnly = false,
    ) {
        if (!$flowReadOnly) {
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
     * @return FlowRun[]
     */
    public function runs(): array
    {
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
     * @param class-string<StubInterface> $stubSource
     * @return FlowMessage[]
     */
    public function executableMessages(string $stubSource): array
    {
        Assert::classString($stubSource, StubInterface::class);

        $flowMessages = array_filter(
            $this->flowMessages,
            static fn (FlowMessage $flowMessage): bool => $flowMessage->getStubSource() === $stubSource
                && $flowMessage->getMessageType() === MessageTypeEnum::WAIT,
        );

        $stub = $this->flowSchema->stubBySource($stubSource);
        $stubMessageClasses = $stub->getMessages();
        $flowMessageClasses = array_map(
            static fn (FlowMessage $flowMessage): string => $flowMessage->getMessageSource(),
            $flowMessages,
        );

        sort($stubMessageClasses);
        sort($flowMessageClasses);

        if ($stubMessageClasses !== $flowMessageClasses) {
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

        $flowSchema = FlowSchema::create($this->flowSource);

        return $this->flowSchemaHash === $flowSchema->getHash();
    }

    public function isReadOnly(): bool
    {
        return $this->flowReadOnly;
    }

    public function status(): StatusEnum
    {
        if ($this->flowRuns === []) {
            return StatusEnum::IN_PROGRESS;
        }

        $flowRuns = count($this->flowRuns);
        $flowRun = end($this->flowRuns);
        $lastRuntimeHash = $flowRun->getFlowRuntimeHash();
        $lastRunTime = $flowRun->getTime();
        $leafStubs = $this->flowSchema->getLeafStubs();
        $leafStubsClassStrings = array_map(
            static fn (Stub $stub): string => $stub->getSource(),
            $leafStubs,
        );

        $status = StatusEnum::IN_PROGRESS;

        foreach ($this->flowMessages as $flowMessage) {
            if ($flowMessage->getFlowRuntimeHash() !== $lastRuntimeHash) {
                continue;
            }

            $arrayKey = array_search($flowMessage->getStubSource(), $leafStubsClassStrings, true);
            if ($arrayKey === false) {
                continue;
            }

            if ($flowRuns > 1) {
                $leafStubsClassStrings = [];
                continue;
            }

            unset($leafStubsClassStrings[$arrayKey]);
        }

        if ($leafStubsClassStrings === []) {
            $status = StatusEnum::OK;
        }

        $datetime = new DateTimeImmutable('now', $lastRunTime->getTimezone());
        if ($status === StatusEnum::IN_PROGRESS && DateTimeImmutable::createFromInterface($lastRunTime)->modify('+1 hour') < $datetime) {
            $status = StatusEnum::IN_PROGRESS_EXCEEDED;
        }

        foreach ($this->flowResults as $flowResult) {
            if ($leafStubsClassStrings !== []) {
                continue;
            }

            if ($flowResult->getFlowRuntimeHash() !== $lastRuntimeHash) {
                continue;
            }

            $status = $flowResult->getResult() && $status->value < StatusEnum::WARNING->value
                ? $status
                : StatusEnum::WARNING;
        }

        foreach ($this->flowExceptions as $flowException) {
            if ($flowException->getFlowRuntimeHash() !== $lastRuntimeHash) {
                continue;
            }

            $status = StatusEnum::FAILED;
        }

        return $status;
    }

    /**
     * @return array<string, null|bool|string|array<FlowMessage|FlowException|FlowResult|FlowRun>|FlowSchema>
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
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
            'flowMessages' => $this->flowMessages,
            'flowExceptions' => $this->flowExceptions,
            'flowResults' => $this->flowResults,
            'flowRuns' => $this->runs(),
            'flowStatus' => $this->status()->name,
            'isExecutable' => $this->isExecutable(),
            'isReadOnly' => $this->flowReadOnly,
        ];
    }
}
