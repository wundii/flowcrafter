<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use InvalidArgumentException;
use RuntimeException;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;

if (PHP_VERSION_ID < 80300) {
    function json_validate(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

final class Converter
{
    public static function jsonToFlow(string $json): Flow
    {
        if (!json_validate($json)) {
            throw new InvalidArgumentException('Invalid JSON provided.');
        }

        $array = json_decode($json, true);
        $flow = Assert::array($array, 'Decoded JSON is not an array.');

        return new Flow(
            Assert::classString($flow['source'] ?? null, FlowInterface::class, 'FlowSource must be a string.'),
            Assert::string($flow['type'] ?? null, 'Type must be a string.'),
            FlowSchema::create(Assert::classString($flow['source'] ?? null, FlowInterface::class)),
            Assert::datetimeImmutable($flow['time'] ?? null, 'Time must be a valid date string.'),
            Assert::string($flow['hash'] ?? null, 'Hash must be a string.'),
            Assert::nullOrString($flow['subject'] ?? null, 'Subject must be null or string.'),
            array_map(
                static function (mixed $array): Message {
                    $stub = Assert::array($array, 'Each Message must be an array.');

                    return new Message(
                        Assert::string($stub['flowHash'] ?? null, 'Each Message must have a string flowHash.'),
                        MessageTypeEnum::from(Assert::string($stub['messageType'] ?? null, 'Each Message must have a string messageType.')),
                        Assert::classString($stub['source'] ?? null, MessageInterface::class, 'Each Message must have a string source.'),
                        Assert::object($stub['message'] ?? null, MessageInterface::class, 'Each Message must have an MessageInterface message.'),
                        Assert::datetimeImmutable($stub['time'] ?? null, 'Each Message must have a valid time date string.'),
                        Assert::string($stub['hash'] ?? null, 'Each Message must have a string hash.'),
                        Assert::string($stub['predecessorHash'] ?? null, 'Each Message must have a string predecessorHash.'),
                    );
                },
                Assert::array($flow['messages'] ?? [], 'Messages must be an array.'),
            ),
        );
    }

    public static function flowToJson(Flow $flow): string
    {
        $json = json_encode($flow);
        if ($json === false) {
            throw new RuntimeException('Failed to encode Flow to JSON.');
        }

        return $json;
    }
}
