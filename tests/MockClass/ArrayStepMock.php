<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class ArrayStepMock implements StepInterface
{
    public function __construct(
        private readonly MessageWithArrayMock $messageWithArrayMock,
    ) {
    }

    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [
            MessageReturnMock::class,
        ];
    }

    public function process(): MessageReturnInterface
    {
        $stations = array_map(
            static fn (StationPriceMock $stationPriceMock): string => $stationPriceMock->getStation(),
            $this->messageWithArrayMock->getStationPrice(),
        );

        return new MessageReturnMock(implode(',', [...$stations, ...$this->messageWithArrayMock->getTags()]));
    }
}
