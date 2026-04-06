<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

class FlowTypeStatsEntity
{
    public function __construct(
        public string $prefix,
        public string $flowType,
        public int $total,
        public int $failed,
        public ?int $successRate,
        public ?string $lastTime,
    ) {
    }
}
