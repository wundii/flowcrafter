<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

abstract class AbstractStub implements StubInterface
{
    /**
     * @var array<MessageInitInterface|MessageDataInterface>
     */
    private array $messages = [];

    public function __construct(
        private string $type,
        MessageInitInterface|MessageDataInterface ...$messages,
    ) {
        $this->messages = $messages;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<MessageInitInterface|MessageDataInterface>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getInitMessage(): ?MessageInitInterface
    {
        foreach ($this->messages as $message) {
            if ($message instanceof MessageInitInterface) {
                return $message;
            }
        }

        return null;
    }

    /**
     * @return MessageDataInterface[]
     */
    public function getDataMessages(): array
    {
        return array_filter($this->messages, static fn (MessageInterface $message): bool => $message instanceof MessageDataInterface);
    }
}
