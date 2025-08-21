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
     * @var class-string[]
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

        $stubBuilder = new StubBuilder($stub, $messages);
        $this->stubs[] = $stubBuilder->getStub();
    }

    /**
     * @param class-string[] $exceptions
     */
    public function exception(StubInterface $exceptionStub, array $exceptions, StubInterface $stub): void
    {
        $exceptionBuilder = new ExceptionBuilder($exceptionStub, $exceptions);
        $exceptionBuilder->execute($stub);
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
