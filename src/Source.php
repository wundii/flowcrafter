<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use Wundii\Flowcrafter\Interface\StepInterface;
use Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity;
use Wundii\Flowcrafter\Storage\Entity\StepSourceEntity;

class Source
{
    /**
     * @var array<class-string, StepSourceEntity>
     */
    private static array $stepCache = [];

    /**
     * @var array<class-string, MessageSourceEntity>
     */
    private static array $messageCache = [];

    /**
     * @param class-string $source
     */
    public static function step(string $source): StepSourceEntity
    {
        if (array_key_exists($source, self::$stepCache)) {
            return self::$stepCache[$source];
        }

        if (!class_exists($source)) {
            throw new InvalidArgumentException(sprintf('Source class "%s" does not exist.', $source));
        }

        if (!is_subclass_of($source, StepInterface::class)) {
            throw new InvalidArgumentException(sprintf('Source class "%s" does not implement step interface.', $source));
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

        $stepSourceEntity = new StepSourceEntity(
            stepHash: md5($fileContent),
            stepSource: $source,
            sourceContent: $fileContent,
            time: new DateTimeImmutable('now'),
        );

        self::$stepCache[$source] = $stepSourceEntity;

        return $stepSourceEntity;
    }

    /**
     * @param class-string $messageClass
     */
    public static function message(string $messageClass): MessageSourceEntity
    {
        if (array_key_exists($messageClass, self::$messageCache)) {
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

                $paramName = $reflectionParameter->getName();
                $reflectionType = $reflectionParameter->getType();
                if ($reflectionType instanceof ReflectionNamedType && class_exists($reflectionType->getName())) {
                    $paramName .= ':' . (new ReflectionClass($reflectionType->getName()))->getShortName();
                }

                $names[] = $paramName;

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
