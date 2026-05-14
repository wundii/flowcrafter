<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class DisconnectedStepMock implements StepInterface
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
            MessageReturnMock::class,
        ];
    }

    public function process(): MessageReturnInterface
    {
        return new MessageReturnMock($this->messageDataSecondMock->getData(), 'test');
    }
}
