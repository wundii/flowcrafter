<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

class FlowException implements JsonSerializable
{
    public function __construct(
        private readonly string $flowHash,
        private readonly string $flowRuntimeHash,
        private readonly string $stubSource,
        private readonly int $code,
        private readonly string $message,
        private readonly string $file,
        private readonly int $line,
        private readonly string $traceString,
        private readonly DateTimeInterface $time,
        private readonly string $hash,
    ) {
    }

    public static function create(
        string $flowHash,
        string $flowRuntimeHash,
        string $stubSource,
        int $code,
        string $message,
        string $file,
        int $line,
        string $traceString,
        ?DateTimeInterface $time = null,
        ?string $hash = null,
    ): self {
        $time = $time ?? new DateTimeImmutable();

        return new self(
            flowHash: $flowHash,
            flowRuntimeHash: $flowRuntimeHash,
            stubSource: $stubSource,
            code: $code,
            message: $message,
            file: $file,
            line: $line,
            traceString: $traceString,
            time: $time,
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

    public function getCode(): int
    {
        return $this->code;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getTraceString(): string
    {
        return $this->traceString;
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
     * @return array<string, string|mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'flowHash' => $this->flowHash,
            'flowRuntimeHash' => $this->flowRuntimeHash,
            'stubSource' => $this->stubSource,
            'code' => $this->code,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'traceString' => $this->traceString,
            'time' => $this->time->format(DateTimeInterface::ATOM),
            'hash' => $this->hash,
        ];
    }
}
