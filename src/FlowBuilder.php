<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\StubInterface;

readonly class FlowBuilder
{
    public function __construct(
        private string $type,
        // private MessageInterface $message,
    ) {
    }

    public function addStub(StubInterface $stub): StubBuilder
    {
        return new StubBuilder();
    }

    /**
     * @param class-string[] $exceptionClasses
     */
    public function exception(StubInterface $stub, array $exceptionClasses): ExceptionBuilder
    {
        return new ExceptionBuilder($stub, $exceptionClasses);
    }

    public function build(): FlowSchema
    {
        return new FlowSchema($this->type);
    }
}
