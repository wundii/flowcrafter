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
        $flowSchema = Assert::array($flow['flowSchema'] ?? null, 'Decoded JSON is not an array.');

        return new Flow(
            Assert::string($flow['flowType'] ?? null, 'Type must be a string.'),
            Assert::classString($flow['flowSource'] ?? null, FlowInterface::class, 'FlowSource must be a string.'),
            new FlowSchema(
                Assert::string($flowSchema['type'] ?? null, 'Schema type must be a string.'),
                array_map(
                    static function (mixed $array): Stub {
                        $stubArray = Assert::array($array, 'Each stub must be an array.');

                        return new Stub(
                            Assert::classString($stubArray['source'] ?? null, StubInterface::class, 'Each Source must be a string.'),
                            /** @phpstan-ignore-next-line */
                            Assert::array($stubArray['messages'] ?? null, 'Each Messages must be an array.'),
                            /** @phpstan-ignore-next-line */
                            Assert::array($stubArray['returnTypes'] ?? null, 'Each ReturnTypes must be an array.'),
                            MessageEnum::from(Assert::string($stubArray['messageEnum'] ?? null, 'Each MessageEnum must have a string messageEnum.')),
                        );
                    },
                    Assert::array($flowSchema['stubs'] ?? [], 'Stubs must be an array.'),
                )
            ),
            Assert::string($flow['flowSchemaHash'] ?? null, 'FlowSchemaHash must be a string.'),
            Assert::datetimeImmutable($flow['time'] ?? null, 'Time must be a valid date string.'),
            Assert::string($flow['flowHash'] ?? null, 'Hash must be a string.'),
            Assert::nullOrString($flow['flowSubject'] ?? null, 'Subject must be null or string.'),
            array_map(
                static function (mixed $array) use ($dataMapper): FlowMessage {
                    $message = Assert::array($array, 'Each Message must be an array.');

                    $messageData = Assert::array($message['message'] ?? [], 'Each Message must be an array.');
                    $messageSource = Assert::classString($message['messageSource'] ?? null, MessageInterface::class, 'Each Message must have a string source.');

                    $flowMessage = $dataMapper->array($messageData, $messageSource);

                    return new FlowMessage(
                        Assert::string($message['flowHash'] ?? null, 'Each Message must have a string flowHash.'),
                        Assert::string($message['flowRuntimeHash'] ?? null, 'Each Message must have a string flowRuntimeHash.'),
                        Assert::classString($message['stubSource'] ?? null, StubInterface::class, 'Each Message must have a string SubInterface.'),
                        Assert::nullOrString($message['stubHash'] ?? null, 'Each stubHash must have a string or null.'),
                        MessageTypeEnum::from(Assert::string($message['messageType'] ?? null, 'Each Message must have a string messageType.')),
                        $messageSource,
                        Assert::object($flowMessage, MessageInterface::class, 'Each Message must have an MessageInterface message.'),
                        Assert::datetimeImmutable($message['time'] ?? null, 'Each Message must have a valid time date string.'),
                        Assert::string($message['hash'] ?? null, 'Each Message must have a string hash.'),
                        Assert::nullOrString($message['predecessorHash'] ?? null, 'Each Message must have a string predecessorHash.'),
                    );
                },
                Assert::array($flow['flowMessages'] ?? [], 'Messages must be an array.'),
            ),
            array_map(
                static function (mixed $array): FlowException {
                    $exception = Assert::array($array, 'Each Exception must be an array.');

                    return new FlowException(
                        Assert::string($exception['flowHash'] ?? null, 'Each Exception must have a string flowHash.'),
                        Assert::string($exception['flowRuntimeHash'] ?? null, 'Each Exception must have a string flowRuntimeHash.'),
                        Assert::string($exception['flowType'] ?? null, 'Each Exception must have a string flowType.'),
                        Assert::string($exception['stubSource'] ?? null, 'Each Exception must have a string stubSource.'),
                        Assert::nullOrString($exception['stubHash'] ?? null, 'Each stubHash must have a string or null.'),
                        Assert::int($exception['code'] ?? null, 'Each Exception must have an integer code.'),
                        Assert::string($exception['message'] ?? null, 'Each Exception must have a string message.'),
                        Assert::string($exception['file'] ?? null, 'Each Exception must have a string file.'),
                        Assert::int($exception['line'] ?? null, 'Each Exception must have an integer line.'),
                        Assert::string($exception['traceString'] ?? null, 'Each Exception must have a string traceString.'),
                        Assert::datetimeImmutable($exception['time'] ?? null, 'Time must be a valid date string.'),
                        Assert::string($exception['hash'] ?? null, 'Each Exception must have a string hash.'),
                    );
                },
                Assert::array($flow['flowExceptions'] ?? [], 'Exceptions must be an array.'),
            ),
            array_map(
                static function (mixed $array): FlowRun {
                    $run = Assert::array($array, 'Each Run must be an array.');

                    return new FlowRun(
                        Assert::string($run['flowHash'] ?? null, 'Each Run must have a string flowHash.'),
                        Assert::string($run['flowRuntimeHash'] ?? null, 'Each Run must have a string runtimeHash.'),
                        Assert::datetimeImmutable($run['time'] ?? null, 'Time must be a valid date string.'),
                        Assert::nullOrString($run['queueId'] ?? null, 'Each Run must have a null or string queueId.'),
                    );
                },
                Assert::array($flow['flowRuns'] ?? [], 'Runs must be an array.'),
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
}
