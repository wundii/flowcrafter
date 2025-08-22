<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class StubMock implements StubInterface
{
    public function __construct(MessageDataInterface|MessageInitInterface ...$messages)
    {
    }

    public function process(): false|MessageDataInterface|MessageReturnInterface
    {
        return new MessageReturnMock();
    }
}
