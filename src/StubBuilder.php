<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class StubBuilder
{
    /**
     * @var StubInterface[]
     */
    private array $stubs = [];

    public function match(MessageInterface $message, StubInterface $stub): self
    {
        return $this;
    }

    public function matchFail(MessageInterface $message, StubInterface $stub): self
    {
        return $this;
    }

    public function addStub(StubInterface $stub): self
    {
        $this->stubs[] = $stub;

        return $this;
    }

    public function noFail(): self
    {
        return $this;
    }

    public function noMatch(): self
    {
        return $this;
    }

    /**
     * @return StubInterface[]
     */
    public function getStubs(): array
    {
        return $this->stubs;
    }
}
