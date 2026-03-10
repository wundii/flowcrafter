<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;

class FlowEntity
{
    public function __construct(
        public string $flowHash,
        public string $flowType,
        public string $flowSource,
        public ?string $flowSubject,
        public DateTimeInterface $time,
        public int $exceptionCount = 0,
    ) {
    }
}
