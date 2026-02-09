<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractStub;
use Wundii\Flowcrafter\Interface\MessageDataInterface;

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

    public function process(): MessageDataInterface
    {
        return new MessageDataMock('stub data');
    }
}
