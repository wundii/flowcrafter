<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

interface StubInterface
{
    public function __construct(MessageInitInterface|MessageDataInterface ...$messages);

    public function process(): false|MessageDataInterface|MessageReturnInterface;
}
