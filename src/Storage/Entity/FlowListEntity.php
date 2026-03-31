<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;
use Wundii\Flowcrafter\Enum\StatusEnum;

class FlowListEntity
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
}
