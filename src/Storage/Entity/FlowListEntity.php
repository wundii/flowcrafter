<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\StatusEnum;

class FlowListEntity implements JsonSerializable
{
    public function __construct(
        public string $flowHash,
        public string $flowType,
        public string $flowSource,
        public ?string $flowSubject,
        public DateTimeInterface $flowTime,
        public DateTimeInterface $lastTerm,
        public StatusEnum $statusEnum,
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
            'flowTime' => $this->flowTime->format(DateTimeInterface::RFC3339_EXTENDED),
            'lastTerm' => $this->lastTerm->format(DateTimeInterface::RFC3339_EXTENDED),
            'status' => $this->statusEnum->name,
        ];
    }
}
