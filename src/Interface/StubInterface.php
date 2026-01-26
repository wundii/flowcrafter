<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

interface StubInterface
{
    public function __construct(string $type, MessageInitInterface|MessageDataInterface ...$messages);

    public function process(): bool|MessageDataInterface|MessageReturnInterface;

    /**
     * @return class-string<MessageDataInterface|MessageReturnInterface>[]
     */
    public function returnTypes(): array;
}
