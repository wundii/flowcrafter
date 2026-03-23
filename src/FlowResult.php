<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use Wundii\Flowcrafter\Interface\StubInterface;

class FlowResult implements JsonSerializable
{
    /**
     * @param class-string<StubInterface> $stubSource
     */
    public function __construct(
        private readonly string $flowHash,
        private readonly string $flowRuntimeHash,
        private readonly string $stubSource,
        private readonly ?string $stubHash,
        private readonly bool $result,
        private readonly DateTimeInterface $time,
        private readonly string $hash,
        bool $skipClassValidation = false,
    ) {
        if (!$skipClassValidation) {
            Assert::classString(
                $stubSource,
                StubInterface::class,
                sprintf('Message source class "%s" does not implement StubInterface.', $stubSource)
            );
        }
    }

    /**
     * @param class-string<StubInterface> $stubSource
     */
    public static function create(
        string $flowHash,
        string $flowRuntimeHash,
        string $stubSource,
        ?string $stubHash,
        bool $result,
        ?DateTimeInterface $time = null,
        ?string $hash = null,
    ): self {
        return new self(
            flowHash: $flowHash,
            flowRuntimeHash: $flowRuntimeHash,
            stubSource: $stubSource,
            stubHash: $stubHash,
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

    public function getStubSource(): string
    {
        return $this->stubSource;
    }

    public function getStubHash(): ?string
    {
        return $this->stubHash;
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
            'stubSource' => $this->stubSource,
            'stubHash' => $this->stubHash,
            'result' => $this->result,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
            'hash' => $this->hash,
        ];
    }
}
