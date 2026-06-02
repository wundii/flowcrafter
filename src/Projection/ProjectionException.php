<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Projection;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use Wundii\Flowcrafter\Uuid;

class ProjectionException implements JsonSerializable
{
    public function __construct(
        private readonly string $flowHash,
        private readonly string $flowType,
        private readonly string $projectionHandlerClass,
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
        string $flowType,
        string $projectionHandlerClass,
        int $code,
        string $message,
        string $file,
        int $line,
        string $traceString,
        ?DateTimeInterface $time = null,
        ?string $hash = null,
    ): self {
        $time ??= new DateTimeImmutable();

        return new self(
            flowHash: $flowHash,
            flowType: $flowType,
            projectionHandlerClass: $projectionHandlerClass,
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

    public function getFlowType(): string
    {
        return $this->flowType;
    }

    public function getProjectionHandlerClass(): string
    {
        return $this->projectionHandlerClass;
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
     * @return array<string, string|int>
     */
    public function jsonSerialize(): array
    {
        return [
            'flowHash' => $this->flowHash,
            'flowType' => $this->flowType,
            'projectionHandlerClass' => $this->projectionHandlerClass,
            'code' => $this->code,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'traceString' => $this->traceString,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
            'hash' => $this->hash,
        ];
    }
}
