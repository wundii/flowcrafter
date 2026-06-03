<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

class ObserverException implements JsonSerializable
{
    public function __construct(
        private readonly string $flowSource,
        private readonly string $messageSource,
        private readonly ?string $queueId,
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
        string $flowSource,
        string $messageSource,
        ?string $queueId,
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
            flowSource: $flowSource,
            messageSource: $messageSource,
            queueId: $queueId,
            code: $code,
            message: $message,
            file: $file,
            line: $line,
            traceString: $traceString,
            time: $time,
            hash: $hash ?? Uuid::uuid7($time)->toString(),
        );
    }

    public function getFlowSource(): string
    {
        return $this->flowSource;
    }

    public function getMessageSource(): string
    {
        return $this->messageSource;
    }

    public function getQueueId(): ?string
    {
        return $this->queueId;
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
     * @return array<string, string|int|null>
     */
    public function jsonSerialize(): array
    {
        return [
            'flowSource' => $this->flowSource,
            'messageSource' => $this->messageSource,
            'queueId' => $this->queueId,
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
