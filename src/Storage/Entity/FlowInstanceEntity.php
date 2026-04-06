<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;
use Wundii\Flowcrafter\Interface\FlowInterface;

readonly class FlowInstanceEntity
{
    /**
     * @param class-string<FlowInterface> $flowSource
     */
    public function __construct(
        public string $flowHash,
        public string $flowType,
        public string $flowSource,
        public ?string $flowSubject,
        public string $flowSchemaHash,
        public DateTimeInterface $time,
    ) {
    }
}
