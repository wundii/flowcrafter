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
        ];
    }
}
