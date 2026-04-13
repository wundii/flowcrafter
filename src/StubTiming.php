<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

class StubTiming implements JsonSerializable
{
    public function __construct(
        private readonly string $stubSource,
        private readonly DateTimeImmutable $started,
        private readonly DateTimeImmutable $ended,
    ) {
    }

    public function getStubSource(): string
    {
        return $this->stubSource;
    }

    public function getStarted(): DateTimeImmutable
    {
        return $this->started;
    }

    public function getEnded(): DateTimeImmutable
    {
        return $this->ended;
    }

    public function getDuration(): int
    {
        $startMs = $this->started->getTimestamp() * 1000 + (int) $this->started->format('v');
        $endMs = $this->ended->getTimestamp() * 1000 + (int) $this->ended->format('v');

        return max(0, $endMs - $startMs);
    }

    /**
     * @return array<string, string|int>
     */
    public function jsonSerialize(): array
    {
        return [
            'stubSource' => $this->stubSource,
            'started' => $this->started->format(DateTimeInterface::RFC3339_EXTENDED),
            'ended' => $this->ended->format(DateTimeInterface::RFC3339_EXTENDED),
            'duration' => $this->getDuration(),
        ];
    }
}
