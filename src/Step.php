<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Exception;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;
use ReflectionMethod;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class Step implements JsonSerializable
{
    public const DEFAULT_RETRIES = 0;

    public const DEFAULT_DELAY = 200;

    private MessageEnum $messageEnum;

    /**
     * @param class-string<StepInterface> $source
     * @param class-string<MessageInterface>[] $messages
     * @param class-string<MessageDataInterface|MessageReturnInterface>[] $returnTypes
     */
    public function __construct(
        private readonly string $source,
        private readonly array $messages,
        private readonly array $returnTypes,
        private readonly int $retries = self::DEFAULT_RETRIES,
        private readonly int $delay = self::DEFAULT_DELAY,
        private readonly bool $runOnce = false,
        ?MessageEnum $messageEnum = null,
        bool $skipClassValidation = false,
    ) {
        if (!$skipClassValidation) {
            Assert::classString(
                $source,
                StepInterface::class,
                'Source must be an instance of MessageInterface',
            );
        }

        foreach ($messages as $message) {
            if (!$skipClassValidation) {
                Assert::classString(
                    $message,
                    MessageInterface::class,
                    sprintf(
                        '%s: Message "%s" must be an instance of MessageInterface',
                        $source,
                        $message,
                    ),
                );
            }

            if ($messageEnum instanceof MessageEnum) {
                $this->messageEnum = $messageEnum;
                continue;
            }

            $interfaces = class_implements($message);
            if ($interfaces === false) {
                throw new InvalidArgumentException(sprintf('Message "%s" does not exist.', $message));
            }

            $this->messageEnum = match (true) {
                in_array(MessageEnum::INIT->interface(), $interfaces, true) => MessageEnum::INIT,
                in_array(MessageEnum::DATA->interface(), $interfaces, true) => MessageEnum::DATA,
                in_array(MessageEnum::RETURN->interface(), $interfaces, true) => MessageEnum::RETURN,
                default => throw new InvalidArgumentException(sprintf('Message "%s" does not implement any known message interface.', $message)),
            };
        }
    }

    /**
     * @param class-string<StepInterface> $source
     */
    public static function create(
        string $source,
        int $retries = self::DEFAULT_RETRIES,
        int $delay = self::DEFAULT_DELAY,
        bool $runOnce = false,
    ): self {
        $reflectionClass = new ReflectionClass($source);

        try {
            $instance = $reflectionClass->newInstanceWithoutConstructor();
            $returnTypes = $instance->returnTypes();
        } catch (Exception) {
            $returnTypes = [];
        }

        $constructor = $reflectionClass->getConstructor();

        if (!$constructor instanceof ReflectionMethod) {
            throw new InvalidArgumentException('The source class must have a constructor.');
        }

        $constructorParams = $constructor->getParameters();

        $messages = [];
        foreach ($constructorParams as $constructorParam) {
            $type = $constructorParam->getType();
            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }

            $typeName = $type->getName();

            if (!is_subclass_of($typeName, MessageInterface::class)) {
                continue;
            }

            $messages[] = $typeName;
        }

        return new self(
            source: $source,
            messages: $messages,
            returnTypes: $returnTypes,
            retries: max($retries, 0),
            delay: max($delay, 0),
            runOnce: $runOnce,
        );
    }

    /**
     * @return class-string<StepInterface>
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return class-string<MessageInterface>[]
     */
    public function getMessages(?MessageEnum $messageEnum = null): array
    {
        if ($messageEnum instanceof MessageEnum) {
            return array_filter(
                $this->messages,
                fn (string $message): bool => match ($messageEnum) {
                    MessageEnum::INIT => is_subclass_of($message, MessageEnum::INIT->interface()),
                    MessageEnum::DATA => is_subclass_of($message, MessageEnum::DATA->interface()),
                    MessageEnum::RETURN => is_subclass_of($message, MessageEnum::RETURN->interface()),
                },
            );
        }

        return $this->messages;
    }

    public function getMessageEnum(): MessageEnum
    {
        return $this->messageEnum;
    }

    /**
     * @return class-string<MessageDataInterface|MessageReturnInterface>[]
     */
    public function getReturnTypes(): array
    {
        return $this->returnTypes;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function isRunOnce(): bool
    {
        return $this->runOnce;
    }

    /**
     * @return array<string, string|mixed>
     */
    public function jsonSerialize(): array
    {
        $return = [
            'source' => $this->source,
            'messageEnum' => $this->messageEnum->value,
            'messages' => $this->messages,
            'returnTypes' => $this->getReturnTypes(),
        ];

        $messageHashes = $this->resolveMessageHashes();
        if ($messageHashes !== []) {
            $return['messageHashes'] = $messageHashes;
        }

        if ($this->retries !== self::DEFAULT_RETRIES) {
            $return['retries'] = $this->retries;
        }

        if ($this->delay !== self::DEFAULT_DELAY) {
            $return['delay'] = $this->delay;
        }

        if ($this->runOnce) {
            $return['runOnce'] = true;
        }

        return $return;
    }

    /**
     * @return array<class-string, string>
     */
    private function resolveMessageHashes(): array
    {
        $messageHashes = [];

        foreach (array_merge($this->messages, $this->returnTypes) as $messageClass) {
            if (!class_exists($messageClass) || !is_subclass_of($messageClass, AbstractMessage::class)) {
                continue;
            }

            $messageHashes[$messageClass] = Source::message($messageClass)->messageHash;
        }

        ksort($messageHashes);

        return $messageHashes;
    }
}
