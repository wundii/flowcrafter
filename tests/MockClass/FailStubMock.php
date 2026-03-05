<?php

declare(strict_types=1);

namespace Tests\MockClass;

use RuntimeException;
use Wundii\Flowcrafter\AbstractStub;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

class FailStubMock extends AbstractStub
{
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
        throw new RuntimeException('Test Exception');
    }
}
