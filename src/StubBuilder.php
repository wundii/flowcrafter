<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

readonly class StubBuilder
{
    /**
     * @param class-string $stub
     * @param class-string[] $messages
     */
    public function __construct(
        private string $stub,
        private array $messages,
    ) {
        Assert::classString(
            $stub,
            StubInterface::class,
            'Stub must be an instance of StubInterface'
        );

        foreach ($messages as $message) {
            Assert::classString(
                $message,
                MessageInterface::class,
                sprintf(
                    '%s: Message "%s" must be an instance of MessageInterface',
                    $stub,
                    $message,
                ),
            );
        }
    }

    /**
     * @return class-string
     */
    public function getStub(): string
    {
        return $this->stub;
    }

    /**
     * @return class-string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
