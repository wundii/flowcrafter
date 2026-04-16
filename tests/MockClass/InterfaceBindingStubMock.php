<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class InterfaceBindingStubMock implements StubInterface
{
    public function __construct(
        private readonly MessageInitMock $messageInitMock,
        private readonly DependencyInterfaceMock $dependencyInterfaceMock,
    ) {
    }

    public function returnTypes(): array
    {
        return [MessageReturnMock::class];
    }

    public function process(): MessageReturnInterface
    {
        return new MessageReturnMock(
            $this->dependencyInterfaceMock->getValue() . ':' . $this->messageInitMock->getData(),
        );
    }
}
