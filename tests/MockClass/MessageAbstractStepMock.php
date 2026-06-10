<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractMessage;
use Wundii\Flowcrafter\Interface\MessageDataInterface;

readonly class MessageAbstractStepMock extends AbstractMessage implements MessageDataInterface
{
    public function __construct(
        public string $data,
        public string $flowHash,
        public string $flowRuntimeHash,
        public string $flowType,
        public string $flowSchemaHash,
        public string $flowSubject,
    ) {
    }
}
