<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

class FlowStatsEntity
{
    public function __construct(
        public string $date,
        public int $instances,
        public int $runs,
    ) {
    }
}
