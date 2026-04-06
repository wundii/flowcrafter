<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\StatusEnum;

class FlowExceptionListEntity implements JsonSerializable
{
    public function __construct(
        public string $hash,
        public string $flowHash,
        public string $flowRuntimeHash,
        public string $flowType,
        public string $stubSource,
        public string $stubHash,
        public int $code,
        public string $message,
        public string $file,
        public int $line,
        public string $traceString,
        public DateTimeInterface $time,
        public StatusEnum $flowStatus,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'hash' => $this->hash,
            'flowHash' => $this->flowHash,
            'flowRuntimeHash' => $this->flowRuntimeHash,
            'flowType' => $this->flowType,
            'stubSource' => $this->stubSource,
            'stubHash' => $this->stubHash,
            'code' => $this->code,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'traceString' => $this->traceString,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
            'flowStatus' => $this->flowStatus->name,
        ];
    }
}
