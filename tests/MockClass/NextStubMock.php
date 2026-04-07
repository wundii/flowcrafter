<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\StubInterface;

class NextStubMock implements StubInterface
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
        return [];
    }

    public function process(): bool
    {
        return $this->messageDataMock->getData() !== '';
    }
}
