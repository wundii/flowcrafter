<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;
use JsonSerializable;

class FlowTypeStatsEntity implements JsonSerializable
{
    public function __construct(
        public string $prefix,
        public string $flowType,
        public int $total,
        public int $failed,
        public ?int $successRate,
        public ?DateTimeInterface $lastTime,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'prefix' => $this->prefix,
            'flowType' => $this->flowType,
            'total' => $this->total,
            'failed' => $this->failed,
            'successRate' => $this->successRate,
            'lastTime' => $this->lastTime?->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
