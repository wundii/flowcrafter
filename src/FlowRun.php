<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

class FlowRun implements JsonSerializable
{
    public function __construct(
        private readonly string $flowHash,
        private readonly string $flowRuntimeHash,
        private readonly DateTimeInterface $time,
        private readonly ?string $queueId,
    ) {
    }

    public static function create(
        string $flowHash,
        string $flowRuntimeHash,
        ?DateTimeInterface $time = null,
        ?string $queueId = null,
    ): self {
        return new self(
            flowHash: $flowHash,
            flowRuntimeHash: $flowRuntimeHash,
            time: $time ?? new DateTimeImmutable(),
            queueId: $queueId,
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

    public function getTime(): DateTimeInterface
    {
        return $this->time;
    }

    public function getQueueId(): ?string
    {
        return $this->queueId;
    }

    /**
     * @return array<string, string|mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'flowHash' => $this->flowHash,
            'flowRuntimeHash' => $this->flowRuntimeHash,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
            'queueId' => $this->queueId,
        ];
    }
}
