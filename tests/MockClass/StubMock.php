<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\MessageStubInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class StubMock implements StubInterface
{
    public function __construct(MessageStubInterface|MessageInitInterface ...$messages)
    {
    }

    public function process(): false|MessageStubInterface|MessageReturnInterface
    {
        return new MessageReturnMock();
    }
}
