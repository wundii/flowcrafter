<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;
use JsonSerializable;

class ScheduleExceptionListEntity implements JsonSerializable
{
    public function __construct(
        public string $hash,
        public string $scheduleClass,
        public string $scheduleName,
        public string $scheduleExpression,
        public int $code,
        public string $message,
        public string $file,
        public int $line,
        public string $traceString,
        public DateTimeInterface $time,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'hash' => $this->hash,
            'scheduleClass' => $this->scheduleClass,
            'scheduleName' => $this->scheduleName,
            'scheduleExpression' => $this->scheduleExpression,
            'code' => $this->code,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'traceString' => $this->traceString,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
