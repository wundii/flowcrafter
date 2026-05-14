<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\StepInterface;

class BoolStepMock implements StepInterface
{
    public function __construct(
        private readonly MessageInitMock $messageInitMock,
    ) {
    }

    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [];
    }

    public function process(): bool
    {
        return $this->messageInitMock->getData() !== '';
    }
}
