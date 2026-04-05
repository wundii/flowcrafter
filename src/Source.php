<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use Wundii\Flowcrafter\Interface\StubInterface;
use Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;

class Source
{
    /**
     * @var array<class-string, StubSourceEntity>
     */
    private static array $stubCache = [];

    /**
     * @var array<class-string, MessageSourceEntity>
     */
    private static array $messageCache = [];

    /**
     * @param class-string $source
     */
    public static function stub(string $source): StubSourceEntity
    {
        if (isset(self::$stubCache[$source])) {
            return self::$stubCache[$source];
        }

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

        $stubSourceEntity = new StubSourceEntity(
            stubHash: md5($fileContent),
            stubSource: $source,
            sourceContent: $fileContent,
            time: new DateTimeImmutable('now'),
        );

        self::$stubCache[$source] = $stubSourceEntity;

        return $stubSourceEntity;
    }

    /**
     * @param class-string $messageClass
     */
    public static function message(string $messageClass): MessageSourceEntity
    {
        if (isset(self::$messageCache[$messageClass])) {
            return self::$messageCache[$messageClass];
        }

        if (!class_exists($messageClass)) {
            throw new InvalidArgumentException(sprintf('Message class "%s" does not exist.', $messageClass));
        }

        if (!is_subclass_of($messageClass, AbstractMessage::class)) {
            throw new InvalidArgumentException(sprintf('Message class "%s" does not extend AbstractMessage.', $messageClass));
        }

        $result = [];
        $reflectionClass = new ReflectionClass($messageClass);
        $constructor = $reflectionClass->getConstructor();

        $names = [];
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $reflectionParameter) {
                if (!$reflectionParameter->isPromoted()) {
                    continue;
                }

                $names[] = $reflectionParameter->getName();

                $reflectionType = $reflectionParameter->getType();
                if (!$reflectionType instanceof ReflectionNamedType) {
                    continue;
                }

                $typeName = $reflectionType->getName();
                if (!class_exists($typeName)) {
                    continue;
                }

                if (!is_subclass_of($typeName, AbstractMessage::class)) {
                    continue;
                }

                $result = array_merge($result, self::message($typeName)->propertyNames);
            }
        }

        sort($names);
        $result[$reflectionClass->getShortName()] = $names;
        ksort($result);

        $resultJson = json_encode($result);
        if ($resultJson === false) {
            throw new InvalidArgumentException(sprintf('Message class "%s" is unreadable', $messageClass));
        }

        $messageSourceEntity = new MessageSourceEntity(
            messageHash: md5($resultJson),
            messageSource: $messageClass,
            propertyNames: $result,
            time: new DateTimeImmutable('now'),
        );

        self::$messageCache[$messageClass] = $messageSourceEntity;

        return $messageSourceEntity;
    }
}
