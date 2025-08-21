<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\StubInterface;

readonly class ExceptionBuilder
{
    /**
     * @param class-string[] $exceptionClasses
     */
    public function __construct(
        private StubInterface $stub,
        private array $exceptionClasses,
    ) {
    }

    public function getStub(): StubInterface
    {
        return $this->stub;
    }

    /**
     * @return class-string[]
     */
    public function getExceptionClasses(): array
    {
        return $this->exceptionClasses;
    }

    public function execute(StubInterface $stub): void
    {
    }
}
