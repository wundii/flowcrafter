<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;
use JsonSerializable;
use Wundii\Flowcrafter\Interface\FlowInterface;

readonly class FlowInstanceEntity implements JsonSerializable
{
    /**
     * @param class-string<FlowInterface> $flowSource
     */
    public function __construct(
        public string $flowHash,
        public string $flowType,
        public string $flowSource,
        public ?string $flowSubject,
        public string $flowSchemaHash,
        public DateTimeInterface $time,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'flowHash' => $this->flowHash,
            'flowType' => $this->flowType,
            'flowSource' => $this->flowSource,
            'flowSubject' => $this->flowSubject,
            'flowSchemaHash' => $this->flowSchemaHash,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
