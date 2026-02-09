<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractStub;
use Wundii\Flowcrafter\Interface\MessageDataInterface;

class OtherStubMock extends AbstractStub
{
    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [
            MessageDataSecondMock::class,
        ];
    }

    public function process(): MessageDataInterface
    {
        return new MessageDataSecondMock('End of flow');
    }
}
