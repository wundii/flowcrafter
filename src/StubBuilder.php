<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

readonly class StubBuilder
{
    /**
     * @param MessageInterface[] $messages
     */
    public function __construct(
        private StubInterface $stub,
        private array $messages,
    ) {
    }

    public function getStub(): StubInterface
    {
        return $this->stub;
    }

    /**
     * @return MessageInterface[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
