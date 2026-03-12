<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use InvalidArgumentException;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class FlowBuilder
{
    /**
     * @var Stub[]
     */
    private array $stubs = [];

    /**
     * @var class-string<MessageInterface>[]
     */
    private array $messages = [];

    /**
     * @param class-string<MessageInitInterface> $messageInit
     * @param class-string<MessageReturnInterface> $messageReturn
     */
    public function __construct(
        private readonly string $type,
        private readonly string $messageInit,
        private readonly string $messageReturn,
    ) {
        Assert::classString(
            $this->messageInit,
            MessageEnum::INIT->interface(),
            'Message must be an instance of MessageInitInterface'
        );

        Assert::classString(
            $this->messageReturn,
            MessageEnum::RETURN->interface(),
            'Message must be an instance of MessageReturnInterface'
        );
    }

    /**
     * @param class-string<StubInterface> $stub
     */
    public function addStub(string $stub): void
    {
        Assert::classString(
            $stub,
            StubInterface::class,
            'Stub must be an instance of StubInterface'
        );

        $stub = Stub::create($stub);

        $this->messages = array_merge($this->messages, $stub->getMessages());
        $this->stubs[] = $stub;
    }

    public function build(): FlowSchema
    {
        if (!in_array($this->messageInit, $this->messages, true)) {
            throw new InvalidArgumentException(sprintf(
                'MessageInit "%s" is not added to the flow.',
                $this->messageInit,
            ));
        }

        $returnTypes = [];
        foreach ($this->stubs as $stub) {
            $returnTypes = array_merge($returnTypes, $stub->getReturnTypes());
        }

        if (!in_array($this->messageReturn, array_unique($returnTypes), true)) {
            throw new InvalidArgumentException(sprintf(
                'MessageReturn "%s" is not added to the flow.',
                $this->messageReturn,
            ));
        }

        return new FlowSchema($this->type, $this->stubs);
    }
}
