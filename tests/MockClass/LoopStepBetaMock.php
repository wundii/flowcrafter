<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class LoopStepBetaMock implements StepInterface
{
    public function __construct(
        private readonly MessageDataMock $messageDataMock,
    ) {
    }

    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [
            MessageDataSecondMock::class,
        ];
    }

    public function process(): MessageDataInterface
    {
        return new MessageDataSecondMock($this->messageDataMock->getData(), new MessageSubDataMock('sub'));
    }
}
