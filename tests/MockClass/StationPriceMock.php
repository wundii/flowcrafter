<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractMessage;

readonly class StationPriceMock extends AbstractMessage
{
    public function __construct(
        private string $station,
        private float $price,
    ) {
    }

    public function getStation(): string
    {
        return $this->station;
    }

    public function getPrice(): float
    {
        return $this->price;
    }
}
