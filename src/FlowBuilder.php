<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use InvalidArgumentException;
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
     * @var class-string[]
     */
    private array $messages = [];

    public function __construct(
        private readonly string $type,
        private readonly string $messageInit,
        private readonly string $messageReturn,
    ) {
        Assert::classString(
            $this->messageInit,
            MessageInitInterface::class,
            'Message must be an instance of MessageInitInterface'
        );

        Assert::classString(
            $this->messageReturn,
            MessageReturnInterface::class,
            'Message must be an instance of MessageReturnInterface'
        );
    }

    /**
     * @param class-string $stub
     * @param class-string ...$messages
     */
    public function addStub(string $stub, string ...$messages): void
    {
        Assert::classString(
            $stub,
            StubInterface::class,
            'Stub must be an instance of StubInterface'
        );

        foreach ($messages as $message) {
            $this->messages[] = Assert::classString(
                $message,
                MessageInterface::class,
                sprintf(
                    '%s: Message "%s" must be an instance of MessageInterface',
                    $stub,
                    $message,
                ),
            );
        }

        $this->stubs[] = Stub::create(
            source: $stub,
            messages: $messages,
        );
    }

    public function build(): FlowSchema
    {
        if (!in_array($this->messageInit, $this->messages, true)) {
            throw new InvalidArgumentException(sprintf(
                'MessageInit "%s" is not added to the flow.',
                $this->messageInit,
            ));
        }

        /**
         * @todo es wird noch ein check für den MessageReturn benötigt
         */
        if (!in_array($this->messageReturn, $this->messages, true)) {
            // throw new InvalidArgumentException(sprintf(
            //     'MessageReturn "%s" is not added to the flow.',
            //     $this->messageReturn,
            // ));
        }

        return new FlowSchema($this->type, $this->stubs);
    }
}
