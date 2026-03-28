<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;
use JsonSerializable;

class StubSourceEntity implements JsonSerializable
{
    /**
     * @param class-string $stubSource
     */
    public function __construct(
        public string $stubHash,
        public string $stubSource,
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
            'stubHash' => $this->stubHash,
            'stubSource' => $this->stubSource,
            'sourceContent' => $this->sourceContent,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
