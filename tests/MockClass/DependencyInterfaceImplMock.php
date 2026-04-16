<?php

declare(strict_types=1);

namespace Tests\MockClass;

class DependencyInterfaceImplMock implements DependencyInterfaceMock
{
    public function getValue(): string
    {
        return 'interface-binding-works';
    }
}
