<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use Wundii\Flowcrafter\Interface\StepInterface;

class FlowResult implements JsonSerializable
{
    /**
     * @param class-string<StepInterface> $stepSource
     */
    public function __construct(
        private readonly string $flowHash,
        private readonly string $flowRuntimeHash,
        private readonly string $stepSource,
        private readonly string $stepHash,
        private readonly bool $result,
        private readonly DateTimeInterface $time,
        private readonly string $hash,
        bool $skipClassValidation = false,
    ) {
        if (!$skipClassValidation) {
            Assert::classString(
                $stepSource,
                StepInterface::class,
                sprintf('Message source class "%s" does not implement StepInterface.', $stepSource)
            );
        }
    }

    /**
     * @param class-string<StepInterface> $stepSource
     */
    public static function create(
        string $flowHash,
        string $flowRuntimeHash,
        string $stepSource,
        string $stepHash,
        bool $result,
        ?DateTimeInterface $time = null,
        ?string $hash = null,
    ): self {
        return new self(
            flowHash: $flowHash,
            flowRuntimeHash: $flowRuntimeHash,
            stepSource: $stepSource,
            stepHash: $stepHash,
            result: $result,
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

    public function getStepHash(): string
    {
        return $this->stepHash;
    }

    public function getResult(): bool
    {
        return $this->result;
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
     * @return array<string, string|bool|null>
     */
    public function jsonSerialize(): array
    {
        return [
            'flowHash' => $this->flowHash,
            'flowRuntimeHash' => $this->flowRuntimeHash,
            'stepSource' => $this->stepSource,
            'stepHash' => $this->stepHash,
            'result' => $this->result,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
            'hash' => $this->hash,
        ];
    }
}
