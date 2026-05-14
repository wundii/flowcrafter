<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;
use JsonSerializable;
use Wundii\Flowcrafter\Interface\StepInterface;

class StepSourceEntity implements JsonSerializable
{
    /**
     * @param class-string<StepInterface> $stepSource
     */
    public function __construct(
        public string $stepHash,
        public string $stepSource,
        public string $sourceContent,
        public DateTimeInterface $time,
    ) {
    }

    /**
     * @return array<string,string>
     */
    public function jsonSerialize(): array
    {
        return [
            'stepHash' => $this->stepHash,
            'stepSource' => $this->stepSource,
            'sourceContent' => $this->sourceContent,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
