<?php

declare(strict_types=1);

namespace Tests\MockClass;

use RuntimeException;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class FailStubMock implements StubInterface
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
        return [
            MessageReturnMock::class,
        ];
    }

    public function process(): MessageReturnInterface
    {
        if (
            str_starts_with($this->messageDataMock->getData(), 'Rind')
            && random_int(1, 10) !== 10
        ) {
            return new MessageReturnMock('success');
        }

        throw new RuntimeException('Test Exception ' . $this->messageDataMock->getData());
    }
}
