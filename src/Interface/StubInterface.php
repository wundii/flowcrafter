<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

interface StubInterface
{
    public function __construct(MessageInitInterface|MessageStubInterface ...$messages);

    public function process(): false|MessageStubInterface|MessageReturnInterface;
}
