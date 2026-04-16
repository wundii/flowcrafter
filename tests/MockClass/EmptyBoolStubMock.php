<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\Interface\StubInterface;

class EmptyBoolStubMock implements StubInterface
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
