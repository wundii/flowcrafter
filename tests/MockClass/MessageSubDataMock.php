<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractMessage;
use Wundii\Flowcrafter\Interface\MessageDataInterface;

readonly class MessageSubDataMock extends AbstractMessage implements MessageDataInterface
{
    public function __construct(
        private string $value,
    ) {
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
