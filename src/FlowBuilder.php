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
     * @var string[]
     */
    private array $stubs = [];

    /**
     * @var string[]
     */
    private array $messageNames = [];

    public function __construct(
        private readonly string $type,
        private readonly string $messageInitClass,
        private readonly string $messageReturnClass,
    ) {
        Assert::classString(
            $this->messageInitClass,
            MessageInitInterface::class,
            'Message must be an instance of MessageInitInterface'
        );

        Assert::classString(
            $this->messageReturnClass,
            MessageReturnInterface::class,
            'Message must be an instance of MessageReturnInterface'
        );
    }

    public function addStub(string $stub, string ...$messages): void
    {
        Assert::classString(
            $stub,
            StubInterface::class,
            'Stub must be an instance of StubInterface'
        );

        foreach ($messages as $message) {
            Assert::classString(
                $message,
                MessageInterface::class,
                sprintf(
                    '%s: Message "%s" must be an instance of MessageInterface',
                    $stub,
                    $message,
                ),
            );

            $this->messageNames[] = $message;
        }

        $stubBuilder = new StubBuilder($stub, $messages);
        $this->stubs[] = $stubBuilder->getStub();
    }

    /**
     * @param class-string[] $exceptionClasses
     */
    public function exception(StubInterface $exceptionStub, array $exceptionClasses, StubInterface $stub): void
    {
        $exceptionBuilder = new ExceptionBuilder($exceptionStub, $exceptionClasses);
        $exceptionBuilder->execute($stub);
    }

    public function build(): FlowSchema
    {
        if (!in_array($this->messageInitClass, $this->messageNames, true)) {
            throw new InvalidArgumentException(sprintf(
                'MessageInit "%s" is not added to the flow.',
                $this->messageInitClass,
            ));
        }

        /**
         * @todo es wird noch ein check für den MessageReturn benötigt
         */
        if (!in_array($this->messageReturnClass, $this->messageNames, true)) {
            // throw new InvalidArgumentException(sprintf(
            //     'MessageReturn "%s" is not added to the flow.',
            //     $this->messageReturn,
            // ));
        }

        return new FlowSchema($this->type, $this->stubs);
    }
}
