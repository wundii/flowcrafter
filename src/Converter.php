<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use InvalidArgumentException;
use RuntimeException;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;

if (PHP_VERSION_ID < 80300) {
    function json_validate(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

final class Converter
{
    public static function JsonToFlow(string $json): Flow
    {
        if (!json_validate($json)) {
            throw new InvalidArgumentException('Invalid JSON provided.');
        }

        /**
         * @todo validations
         */

        $array = json_decode($json, true);
        $flow = Assert::array($array, 'Decoded JSON is not an array.');

        return new Flow(
            Assert::string($flow['source'] ?? '', 'FlowSource must be a string.'),
            Assert::datetimeImmutable($flow['time'] ?? '', 'CreatedAt must be a valid date string.'),
            Assert::string($flow['hash'] ?? '', 'Hash must be a string.'),
            array_map(
                static function (mixed $array): Message {
                    $stub = Assert::array($array, 'Each Message must be an array.');

                    return new Message(
                        Assert::string($stub['flowHash'] ?? '', 'Each Message must have a string flowHash.'),
                        MessageTypeEnum::from(Assert::string($stub['messageType'] ?? '', 'Each Message must have a string messageType.')),
                        Assert::string($stub['source'] ?? '', 'Each Message must have a string source.'),
                        Assert::array($stub['data'] ?? [], 'Each Message must have an array of data.'),
                        Assert::datetimeImmutable($stub['time'] ?? '', 'Each Message must have a valid createdAt date string.'),
                        Assert::string($stub['hash'] ?? '', 'Each Message must have a string hash.'),
                        Assert::string($stub['predecessorHash'] ?? null, 'Each Message must have a string predecessorHash.'),
                    );
                },
                Assert::array($flow['messages'] ?? [], 'Messages must be an array.'),
            ),
        );
    }

    public static function FlowToJson(Flow $flow): string
    {
        $json = json_encode($flow);
        if ($json === false) {
            throw new RuntimeException('Failed to encode Flow to JSON.');
        }

        return $json;
    }
}
