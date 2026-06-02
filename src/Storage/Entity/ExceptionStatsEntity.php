<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use JsonSerializable;

class ExceptionStatsEntity implements JsonSerializable
{
    public function __construct(
        public string $date,
        public int $flow,
        public int $schedule,
        public int $observer = 0,
        public int $projection = 0,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'date' => $this->date,
            'flow' => $this->flow,
            'schedule' => $this->schedule,
            'observer' => $this->observer,
            'projection' => $this->projection,
        ];
    }
}
