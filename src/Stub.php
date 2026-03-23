<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;
use ReflectionMethod;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;

class Stub implements JsonSerializable
{
    private MessageEnum $messageEnum;

    /**
     * @param class-string<StubInterface> $source
     * @param class-string<MessageInterface>[] $messages
     * @param class-string<MessageDataInterface|MessageReturnInterface>[] $returnTypes
     */
    public function __construct(
        private readonly string $source,
        private readonly array $messages,
        private readonly array $returnTypes,
        ?MessageEnum $messageEnum = null,
        bool $skipClassValidation = false,
    ) {
        if (!$skipClassValidation) {
            Assert::classString(
                $source,
                StubInterface::class,
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
     * @param class-string<StubInterface> $source
     */
    public static function create(
        string $source,
    ): self {

        try {
            $reflectionClass = new ReflectionClass($source);
            $instance = $reflectionClass->newInstanceWithoutConstructor();
            $returnTypes = $instance->returnTypes();
        } catch (Exception) {
            $returnTypes = [];
        }

        $reflectionClass = new ReflectionClass($source);
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
        );
    }

    /**
     * @param class-string $source
     */
    public static function source(string $source): StubSourceEntity
    {
        if (!class_exists($source)) {
            throw new InvalidArgumentException(sprintf('Source class "%s" does not exist.', $source));
        }

        if (!is_subclass_of($source, StubInterface::class)) {
            throw new InvalidArgumentException(sprintf('Source class "%s" does not implement stub interface.', $source));
        }

        $reflectionClass = new ReflectionClass($source);
        $file = $reflectionClass->getFileName();
        if ($file === false) {
            throw new InvalidArgumentException(sprintf('Source class "%s" is not your class.', $source));
        }

        $fileContent = file_get_contents($file);
        if ($fileContent === false) {
            throw new InvalidArgumentException(sprintf('Source class "%s" is unreadable', $source));
        }

        return new StubSourceEntity(
            stubHash: md5($fileContent),
            stubSource: $source,
            sourceContent: $fileContent,
            time: new DateTimeImmutable('now'),
        );
    }

    /**
     * @return class-string<StubInterface>
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

    /**
     * @return array<string, string|mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'source' => $this->source,
            'messageEnum' => $this->messageEnum->value,
            'messages' => $this->messages,
            'returnTypes' => $this->getReturnTypes(),
        ];
    }
}
