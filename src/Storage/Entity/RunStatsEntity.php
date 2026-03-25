<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

class RunStatsEntity
{
    public function __construct(
        public string $date,
        public int $count,
    ) {
    }
}
