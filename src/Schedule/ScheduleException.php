<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Schedule;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use Wundii\Flowcrafter\Uuid;

class ScheduleException implements JsonSerializable
{
    public function __construct(
        private readonly string $scheduleClass,
        private readonly string $scheduleName,
        private readonly string $scheduleExpression,
        private readonly string $code,
        private readonly string $message,
        private readonly string $file,
        private readonly int $line,
        private readonly string $traceString,
        private readonly DateTimeInterface $time,
        private readonly string $hash,
    ) {
    }

    public static function create(
        string $scheduleClass,
        string $scheduleName,
        string $scheduleExpression,
        string $code,
        string $message,
        string $file,
        int $line,
        string $traceString,
        ?DateTimeInterface $time = null,
        ?string $hash = null,
    ): self {
        $time ??= new DateTimeImmutable();

        return new self(
            scheduleClass: $scheduleClass,
            scheduleName: $scheduleName,
            scheduleExpression: $scheduleExpression,
            code: $code,
            message: $message,
            file: $file,
            line: $line,
            traceString: $traceString,
            time: $time,
            hash: $hash ?? Uuid::uuid7($time)->toString(),
        );
    }

    public function getScheduleClass(): string
    {
        return $this->scheduleClass;
    }

    public function getScheduleName(): string
    {
        return $this->scheduleName;
    }

    public function getScheduleExpression(): string
    {
        return $this->scheduleExpression;
    }

    public function getCode(): string
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
            'scheduleClass' => $this->scheduleClass,
            'scheduleName' => $this->scheduleName,
            'scheduleExpression' => $this->scheduleExpression,
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
