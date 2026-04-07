<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use JsonSerializable;

class FlowStatsEntity implements JsonSerializable
{
    public function __construct(
        public string $date,
        public int $instances,
        public int $runs,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'date' => $this->date,
            'instances' => $this->instances,
            'runs' => $this->runs,
        ];
    }
}
