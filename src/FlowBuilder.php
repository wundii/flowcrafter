<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class FlowBuilder
{
    /**
     * @var StubInterface[]
     */
    private array $stubs = [];

    private ?MessageInitInterface $messageInit = null;

    public function __construct(
        private readonly string $type,
    ) {
    }

    public function addStub(StubInterface $stub, MessageInterface ...$messages): void
    {
        $this->setMessageInit(...$messages);

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
        return new FlowSchema($this->type, $this->stubs);
    }

    private function setMessageInit(MessageInterface ...$messages): void
    {
        if ($this->messageInit instanceof MessageInitInterface) {
            return;
        }

        $messageInit = array_filter($messages, fn (MessageInterface $message): bool => $message instanceof MessageInitInterface);

        $this->messageInit = reset($messageInit) ?: null;
    }
}
