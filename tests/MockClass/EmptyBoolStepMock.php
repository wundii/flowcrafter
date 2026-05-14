<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\Interface\StepInterface;

class EmptyBoolStepMock implements StepInterface
{
    public function __construct(
        public readonly EmptyInitMessage $init,
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
        return true;
    }
}
