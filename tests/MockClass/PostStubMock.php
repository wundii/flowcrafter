<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractStub;

class PostStubMock extends AbstractStub
{
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
