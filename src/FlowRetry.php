<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

class FlowRetry implements JsonSerializable
{
    public function __construct(
        private readonly string $flowHash,
        private readonly string $flowRuntimeHash,
        private readonly string $stepSource,
        private readonly int $attempt,
        private readonly string $message,
        private readonly DateTimeInterface $time,
        private readonly string $hash,
    ) {
    }

    public static function create(
        string $flowHash,
        string $flowRuntimeHash,
        string $stepSource,
        int $attempt,
        string $message,
        ?DateTimeInterface $time = null,
        ?string $hash = null,
    ): self {
        return new self(
            flowHash: $flowHash,
            flowRuntimeHash: $flowRuntimeHash,
            stepSource: $stepSource,
            attempt: $attempt,
            message: $message,
            time: $time ?? new DateTimeImmutable(),
            hash: $hash ?? Uuid::uuid7($time)->toString(),
        );
    }

    public function getFlowHash(): string
    {
        return $this->flowHash;
    }

    public function getFlowRuntimeHash(): string
    {
        return $this->flowRuntimeHash;
    }

    public function getStepSource(): string
    {
        return $this->stepSource;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getTime(): DateTimeInterface
    {
        return $this->time;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @return array<string, string|int>
     */
    public function jsonSerialize(): array
    {
        return [
            'flowHash' => $this->flowHash,
            'flowRuntimeHash' => $this->flowRuntimeHash,
            'stepSource' => $this->stepSource,
            'attempt' => $this->attempt,
            'message' => $this->message,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
            'hash' => $this->hash,
        ];
    }
}
