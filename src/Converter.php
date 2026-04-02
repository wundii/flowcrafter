<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use Wundii\DataMapper\DataConfig;
use Wundii\DataMapper\DataMapper;
use Wundii\DataMapper\Enum\ApproachEnum;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

if (PHP_VERSION_ID < 80300) {
    function json_validate(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

final class Converter
{
    /**
     * @param array<class-string, class-string> $messageClassMap
     */
    public static function jsonToFlow(
        string $json,
        array $messageClassMap = [
            DateTimeInterface::class => DateTime::class,
        ],
    ): Flow {
        if (!json_validate($json)) {
            throw new InvalidArgumentException('Invalid JSON provided.');
        }

        $array = json_decode($json, true);
        $flow = Assert::array($array, 'Decoded JSON is not an array.');

        return self::arrayToFlow($flow, $messageClassMap);
    }

    /**
     * @param array<mixed> $flow
     * @param array<class-string, class-string> $messageClassMap
     */
    public static function arrayToFlow(
        array $flow,
        array $messageClassMap = [
            DateTimeInterface::class => DateTime::class,
        ],
    ): Flow {
        $dataConfig = new DataConfig(
            approachEnum: ApproachEnum::CONSTRUCTOR,
            classMap: $messageClassMap,
        );
        $dataMapper = new DataMapper($dataConfig);
        $flowSchemaArray = Assert::array($flow['flowSchema'] ?? null, 'Decoded JSON is not an array.');

        $readOnlyReasons = self::detectReadOnly($flow, $flowSchemaArray);
        $readOnly = $readOnlyReasons !== [];
        $flowSource = $readOnly
            ? Assert::string($flow['flowSource'] ?? null, 'FlowSource must be a string.')
            : Assert::classString($flow['flowSource'] ?? null, FlowInterface::class, 'FlowSource must be a string.');

        return new Flow(
            Assert::string($flow['flowType'] ?? null, 'Type must be a string.'),
            /** @phpstan-ignore argument.type */
            $flowSource,
            new FlowSchema(
                Assert::string($flowSchemaArray['type'] ?? null, 'Schema type must be a string.'),
                array_map(
                    static function (mixed $array) use ($readOnly): Stub {
                        $stubArray = Assert::array($array, 'Each stub must be an array.');
                        $source = $readOnly
                            ? Assert::string($stubArray['source'] ?? null, 'Each Source must be a string.')
                            : Assert::classString($stubArray['source'] ?? null, StubInterface::class, 'Each Source must be a string.');

                        return new Stub(
                            /** @phpstan-ignore argument.type */
                            $source,
                            /** @phpstan-ignore-next-line */
                            Assert::array($stubArray['messages'] ?? null, 'Each Messages must be an array.'),
                            /** @phpstan-ignore-next-line */
                            Assert::array($stubArray['returnTypes'] ?? null, 'Each ReturnTypes must be an array.'),
                            MessageEnum::from(Assert::string($stubArray['messageEnum'] ?? null, 'Each MessageEnum must have a string messageEnum.')),
                            $readOnly,
                        );
                    },
                    Assert::array($flowSchemaArray['stubs'] ?? [], 'Stubs must be an array.'),
                )
            ),
            Assert::string($flow['flowSchemaHash'] ?? null, 'FlowSchemaHash must be a string.'),
            Assert::datetimeImmutable($flow['time'] ?? null, 'Time must be a valid date string.'),
            Assert::string($flow['flowHash'] ?? null, 'Hash must be a string.'),
            Assert::nullOrString($flow['flowSubject'] ?? null, 'Subject must be null or string.'),
            array_map(
                static function (mixed $array) use ($dataMapper, $readOnly): FlowMessage {
                    $message = Assert::array($array, 'Each Message must be an array.');

                    $messageData = Assert::array($message['message'] ?? [], 'Each Message must be an array.');
                    $messageSource = $readOnly
                        ? Assert::string($message['messageSource'] ?? null, 'Each Message must have a string source.')
                        : Assert::classString($message['messageSource'] ?? null, MessageInterface::class, 'Each Message must have a string source.');

                    $flowMessage = $readOnly
                        ? new FlowMessageReadOnly($messageSource, $messageData)
                        /** @phpstan-ignore argument.type */
                        : $dataMapper->array($messageData, $messageSource);

                    $stubSource = $readOnly
                        ? Assert::string($message['stubSource'] ?? null, 'Each Message must have a string SubInterface.')
                        : Assert::classString($message['stubSource'] ?? null, StubInterface::class, 'Each Message must have a string SubInterface.');

                    return new FlowMessage(
                        Assert::string($message['flowHash'] ?? null, 'Each Message must have a string flowHash.'),
                        Assert::string($message['flowRuntimeHash'] ?? null, 'Each Message must have a string flowRuntimeHash.'),
                        /** @phpstan-ignore argument.type */
                        $stubSource,
                        Assert::string($message['stubHash'] ?? '', 'Each stubHash must have a string or null.'),
                        MessageTypeEnum::from(Assert::string($message['messageType'] ?? null, 'Each Message must have a string messageType.')),
                        /** @phpstan-ignore argument.type */
                        $messageSource,
                        Assert::string($message['messageHash'] ?? '', 'Each messageHash must be a string.'),
                        Assert::object($flowMessage, MessageInterface::class, 'Each Message must have an MessageInterface message.'),
                        Assert::datetimeImmutable($message['time'] ?? null, 'Each Message must have a valid time date string.'),
                        Assert::string($message['hash'] ?? null, 'Each Message must have a string hash.'),
                        Assert::nullOrString($message['predecessorHash'] ?? null, 'Each Message must have a string predecessorHash.'),
                        $readOnly,
                    );
                },
                Assert::array($flow['flowMessages'] ?? [], 'Messages must be an array.'),
            ),
            array_map(
                static function (mixed $array) use ($readOnly): FlowException {
                    $exception = Assert::array($array, 'Each Exception must be an array.');

                    return new FlowException(
                        Assert::string($exception['flowHash'] ?? null, 'Each Exception must have a string flowHash.'),
                        Assert::string($exception['flowRuntimeHash'] ?? null, 'Each Exception must have a string flowRuntimeHash.'),
                        Assert::string($exception['flowType'] ?? null, 'Each Exception must have a string flowType.'),
                        Assert::string($exception['stubSource'] ?? null, 'Each Exception must have a string stubSource.'), /** @phpstan-ignore argument.type */
                        Assert::string($exception['stubHash'] ?? '', 'Each stubHash must have a string or null.'),
                        Assert::int($exception['code'] ?? null, 'Each Exception must have an integer code.'),
                        Assert::string($exception['message'] ?? null, 'Each Exception must have a string message.'),
                        Assert::string($exception['file'] ?? null, 'Each Exception must have a string file.'),
                        Assert::int($exception['line'] ?? null, 'Each Exception must have an integer line.'),
                        Assert::string($exception['traceString'] ?? null, 'Each Exception must have a string traceString.'),
                        Assert::datetimeImmutable($exception['time'] ?? null, 'Time must be a valid date string.'),
                        Assert::string($exception['hash'] ?? null, 'Each Exception must have a string hash.'),
                        $readOnly,
                    );
                },
                Assert::array($flow['flowExceptions'] ?? [], 'Exceptions must be an array.'),
            ),
            array_map(
                static function (mixed $array): FlowRun {
                    $run = Assert::array($array, 'Each Run must be an array.');

                    return new FlowRun(
                        Assert::string($run['flowHash'] ?? null, 'Each Run must have a string flowHash.'),
                        Assert::string($run['flowRuntimeHash'] ?? null, 'Each Run must have a string flowRuntimeHash.'),
                        Assert::string($run['flowType'] ?? null, 'Each Run must have a string flowType.'),
                        Assert::datetimeImmutable($run['time'] ?? null, 'Time must be a valid date string.'),
                        Assert::nullOrString($run['queueId'] ?? null, 'Each Run must have a null or string queueId.'),
                    );
                },
                Assert::array($flow['flowRuns'] ?? [], 'Runs must be an array.'),
            ),
            array_map(
                static function (mixed $array) use ($readOnly): FlowResult {
                    $result = Assert::array($array, 'Each Result must be an array.');

                    return new FlowResult(
                        Assert::string($result['flowHash'] ?? null, 'Each Result must have a string flowHash.'),
                        Assert::string($result['flowRuntimeHash'] ?? null, 'Each Result must have a string flowRuntimeHash.'),
                        Assert::string($result['stubSource'] ?? null, 'Each Result must have a string stubSource.'), /** @phpstan-ignore argument.type */
                        Assert::string($result['stubHash'] ?? '', 'Each stubHash must have a string or null.'),
                        Assert::bool($result['result'] ?? null, 'Each Result must have a bool result.'),
                        Assert::datetimeImmutable($result['time'] ?? null, 'Time must be a valid date string.'),
                        Assert::string($result['hash'] ?? null, 'Each Result must have a string hash.'),
                        $readOnly,
                    );
                },
                Assert::array($flow['flowResults'] ?? [], 'Results must be an array.'),
            ),
            $readOnlyReasons,
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

    public static function flowToDiagram(string $path, Flow $flow): string
    {
        if (!is_dir(dirname($path))) {
            throw new InvalidArgumentException(sprintf(
                'Directory "%s" does not exist.',
                dirname($path),
            ));
        }

        $flowSchema = $flow->getSchema();
        $initStub = $flowSchema->initStub();
        $subject = $flow->getSubject();
        $title = $flow->getType();
        if ($subject) {
            $title .= sprintf(' - %s', $subject);
        }

        $output = sprintf(
            "---\ntitle: %s\ntheme: neo\n---\nstateDiagram-v2\n[*]-->%s: %s\n",
            $title,
            $initStub->getSource(),
            $initStub->getMessages(MessageEnum::INIT)[0],
        );

        foreach ($flowSchema->stubs() as $stub) {
            foreach ($stub->getReturnTypes() as $messageClass) {
                foreach ($flowSchema->stubByMessageClass($messageClass) as $nextStub) {
                    $output .= sprintf(
                        "%s-->%s: %s\n",
                        $stub->getSource(),
                        $nextStub->getSource(),
                        $messageClass,
                    );
                }

                if (is_subclass_of($messageClass, MessageEnum::RETURN->interface())) {
                    $output .= sprintf(
                        "%s-->[*]: %s\n",
                        $stub->getSource(),
                        $messageClass,
                    );
                }
            }
        }

        $filename = $path . $flow->getType() . '.mmd';

        if (!file_put_contents($filename, $output)) {
            throw new RuntimeException(sprintf(
                'Failed to write diagram to file "%s".',
                $filename,
            ));
        }

        return $filename;
    }

    private static function displayValue(mixed $value): string
    {
        return is_string($value) ? $value : gettype($value);
    }

    /**
     * @param class-string $interface
     * @return string[] empty when valid
     */
    private static function validateClassSource(mixed $value, string $interface, string $label): array
    {
        if (!is_string($value)) {
            return [sprintf("%s '%s' is not a string", $label, gettype($value))];
        }

        if (!class_exists($value)) {
            return [sprintf("%s '%s' class does not exist", $label, $value)];
        }

        if (!is_subclass_of($value, $interface)) {
            $short = substr(strrchr($interface, '\\') ?: $interface, 1);

            return [sprintf("%s '%s' does not implement %s", $label, $value, $short)];
        }

        return [];
    }

    /**
     * @param array<mixed> $flow
     * @param array<mixed> $flowSchemaArray
     * @return string[]
     */
    private static function detectReadOnly(array $flow, array $flowSchemaArray): array
    {
        $reasons = [];

        $flowSource = $flow['flowSource'] ?? null;
        $reasons = [...$reasons, ...self::validateClassSource($flowSource, FlowInterface::class, 'flowSource')];

        foreach (Assert::array($flowSchemaArray['stubs'] ?? [], 'Stubs must be an array.') as $stubData) {
            $stubArray = Assert::array($stubData, 'Each stub must be an array.');
            $stubSource = $stubArray['source'] ?? null;
            $reasons = [...$reasons, ...self::validateClassSource($stubSource, StubInterface::class, 'Stub source')];
        }

        foreach (Assert::array($flow['flowMessages'] ?? [], 'Messages must be an array.') as $msgData) {
            $message = Assert::array($msgData, 'Each Message must be an array.');
            $messageSource = $message['messageSource'] ?? null;
            $messageHash = $message['messageHash'] ?? null;
            $messageErrors = self::validateClassSource($messageSource, MessageInterface::class, 'Message source');
            if ($messageErrors !== []) {
                $reasons = [...$reasons, ...$messageErrors];
                continue;
            }

            $stubSource = $message['stubSource'] ?? null;
            $reasons = [...$reasons, ...self::validateClassSource($stubSource, StubInterface::class, sprintf("Message '%s' stub source", self::displayValue($messageSource)))];

            /** @phpstan-ignore-next-line */
            if ($messageHash !== Source::message($messageSource)->messageHash) {
                $reasons[] = sprintf("Message '%s' property-hash mismatch", self::displayValue($messageSource));
            }
        }

        foreach (Assert::array($flow['flowExceptions'] ?? [], 'Exceptions must be an array.') as $excData) {
            $exception = Assert::array($excData, 'Each Exception must be an array.');
            $stubSource = $exception['stubSource'] ?? null;
            $reasons = [...$reasons, ...self::validateClassSource($stubSource, StubInterface::class, 'Exception stub source')];
        }

        foreach (Assert::array($flow['flowResults'] ?? [], 'Results must be an array.') as $resData) {
            $result = Assert::array($resData, 'Each Result must be an array.');
            $stubSource = $result['stubSource'] ?? null;
            $reasons = [...$reasons, ...self::validateClassSource($stubSource, StubInterface::class, 'Result stub source')];
        }

        return $reasons;
    }
}
