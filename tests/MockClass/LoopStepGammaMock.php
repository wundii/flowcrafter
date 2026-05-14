<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class LoopStepGammaMock implements StepInterface
{
    public function __construct(
        private readonly MessageDataSecondMock $messageDataSecondMock,
    ) {
    }

    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [
            MessageDataMock::class,
        ];
    }

    public function process(): MessageDataInterface
    {
        return new MessageDataMock($this->messageDataSecondMock->getData());
    }
}
