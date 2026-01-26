<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractStub;
use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

class StubMock extends AbstractStub
{
    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [
            MessageDataMock::class,
        ];
    }

    public function process(): false|MessageDataInterface|MessageReturnInterface
    {
        return new MessageDataMock();
    }
}
