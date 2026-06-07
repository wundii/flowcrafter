<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractMessage;
use Wundii\Flowcrafter\Interface\MessageInitInterface;

readonly class MessageWithArrayMock extends AbstractMessage implements MessageInitInterface
{
    /**
     * @param StationPriceMock[] $stationPrice
     * @param string[] $tags
     */
    public function __construct(
        private array $stationPrice,
        private array $tags,
    ) {
    }

    /**
     * @return StationPriceMock[]
     */
    public function getStationPrice(): array
    {
        return $this->stationPrice;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}
