<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Wundii\DataMapper\DataConfig;
use Wundii\DataMapper\DataMapper;
use Wundii\DataMapper\Enum\ApproachEnum;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

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
                array_map(static fn (mixed $a): Step => self::mapStep($a, $readOnly), Assert::array($flowSchemaArray['steps'] ?? [], 'Steps must be an array.')),
            ),
            Assert::string($flow['flowSchemaHash'] ?? null, 'FlowSchemaHash must be a string.'),
            Assert::datetimeImmutable($flow['time'] ?? null, 'Time must be a valid date string.'),
            Assert::string($flow['flowHash'] ?? null, 'Hash must be a string.'),
            Assert::nullOrString($flow['flowSubject'] ?? null, 'Subject must be null or string.'),
            array_map(static fn (mixed $a): FlowMessage => self::mapFlowMessage($a, $dataMapper, $readOnly), Assert::array($flow['flowMessages'] ?? [], 'Messages must be an array.')),
            array_map(static fn (mixed $a): FlowException => self::mapFlowException($a, $readOnly), Assert::array($flow['flowExceptions'] ?? [], 'Exceptions must be an array.')),
            array_map(static fn (mixed $a): FlowRun => self::mapFlowRun($a), Assert::array($flow['flowRuns'] ?? [], 'Runs must be an array.')),
            array_map(static fn (mixed $a): FlowResult => self::mapFlowResult($a, $readOnly), Assert::array($flow['flowResults'] ?? [], 'Results must be an array.')),
            array_map(static fn (mixed $a): FlowRetry => self::mapFlowRetry($a), Assert::array($flow['flowRetries'] ?? [], 'FlowRetries must be an array.')),
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
        $initStep = $flowSchema->initStep();
        $subject = $flow->getSubject();
        $title = $flow->getType();
        if ($subject) {
            $title .= sprintf(' - %s', $subject);
        }

        $output = sprintf(
            "---\ntitle: %s\ntheme: neo\n---\nstateDiagram-v2\n[*]-->%s: %s\n",
            $title,
            $initStep->getSource(),
            $initStep->getMessages(MessageEnum::INIT)[0],
        );

        foreach ($flowSchema->steps() as $step) {
            foreach ($step->getReturnTypes() as $messageClass) {
                foreach ($flowSchema->stepByMessageClass($messageClass) as $nextStep) {
                    $output .= sprintf(
                        "%s-->%s: %s\n",
                        $step->getSource(),
                        $nextStep->getSource(),
                        $messageClass,
                    );
                }

                if (is_subclass_of($messageClass, MessageEnum::RETURN->interface())) {
                    $output .= sprintf(
                        "%s-->[*]: %s\n",
                        $step->getSource(),
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

    private static function mapStep(mixed $array, bool $readOnly): Step
    {
        $stepArray = Assert::array($array, 'Each step must be an array.');
        $source = $readOnly
            ? Assert::string($stepArray['source'] ?? null, 'Each Source must be a string.')
            : Assert::classString($stepArray['source'] ?? null, StepInterface::class, 'Each Source must be a string.');

        return new Step(
            /** @phpstan-ignore argument.type */
            $source,
            /** @phpstan-ignore-next-line */
            Assert::array($stepArray['messages'] ?? null, 'Each Messages must be an array.'),
            /** @phpstan-ignore-next-line */
            Assert::array($stepArray['returnTypes'] ?? null, 'Each ReturnTypes must be an array.'),
            Assert::int($stepArray['retries'] ?? Step::DEFAULT_RETRIES, 'Each retries must be an integer.'),
            Assert::int($stepArray['delay'] ?? Step::DEFAULT_DELAY, 'Each delay must be an integer.'),
            Assert::bool($stepArray['runOnce'] ?? false, 'Each runOnce must be a boolean.'),
            MessageEnum::from(Assert::string($stepArray['messageEnum'] ?? null, 'Each MessageEnum must have a string messageEnum.')),
            $readOnly,
        );
    }

    private static function mapFlowMessage(mixed $array, DataMapper $dataMapper, bool $readOnly): FlowMessage
    {
        $message = Assert::array($array, 'Each Message must be an array.');

        $messageData = Assert::array($message['message'] ?? [], 'Each Message must be an array.');
        $messageSource = $readOnly
            ? Assert::string($message['messageSource'] ?? null, 'Each Message must have a string source.')
            : Assert::classString($message['messageSource'] ?? null, MessageInterface::class, 'Each Message must have a string source.');

        $flowMessage = $readOnly
            ? new ReadonlyMessage($messageSource, $messageData)
            /** @phpstan-ignore argument.type, argument.templateType */
            : $dataMapper->array($messageData, $messageSource);

        $stepSource = $readOnly
            ? Assert::string($message['stepSource'] ?? null, 'Each Message must have a string SubInterface.')
            : Assert::classString($message['stepSource'] ?? null, StepInterface::class, 'Each Message must have a string SubInterface.');

        return new FlowMessage(
            Assert::string($message['flowHash'] ?? null, 'Each Message must have a string flowHash.'),
            Assert::string($message['flowRuntimeHash'] ?? null, 'Each Message must have a string flowRuntimeHash.'),
            Assert::string($message['flowType'] ?? '', 'Each Message must have a string flowType.'),
            /** @phpstan-ignore argument.type */
            $stepSource,
            Assert::string($message['stepHash'] ?? '', 'Each stepHash must have a string or null.'),
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
    }

    private static function mapFlowException(mixed $array, bool $readOnly): FlowException
    {
        $exception = Assert::array($array, 'Each Exception must be an array.');

        $code = $exception['code'] ?? null;
        if (is_int($code)) {
            $code = (string) $code;
        }

        return new FlowException(
            Assert::string($exception['flowHash'] ?? null, 'Each Exception must have a string flowHash.'),
            Assert::string($exception['flowRuntimeHash'] ?? null, 'Each Exception must have a string flowRuntimeHash.'),
            Assert::string($exception['flowType'] ?? null, 'Each Exception must have a string flowType.'),
            Assert::string($exception['stepSource'] ?? null, 'Each Exception must have a string stepSource.'), /** @phpstan-ignore argument.type */
            Assert::string($exception['stepHash'] ?? '', 'Each stepHash must have a string or null.'),
            Assert::string($code, 'Each Exception must have a string code.'),
            Assert::string($exception['message'] ?? null, 'Each Exception must have a string message.'),
            Assert::string($exception['file'] ?? null, 'Each Exception must have a string file.'),
            Assert::int($exception['line'] ?? null, 'Each Exception must have an integer line.'),
            Assert::string($exception['traceString'] ?? null, 'Each Exception must have a string traceString.'),
            Assert::datetimeImmutable($exception['time'] ?? null, 'Time must be a valid date string.'),
            Assert::string($exception['hash'] ?? null, 'Each Exception must have a string hash.'),
            $readOnly,
        );
    }

    private static function mapFlowRun(mixed $array): FlowRun
    {
        $run = Assert::array($array, 'Each Run must be an array.');

        return new FlowRun(
            Assert::string($run['flowHash'] ?? null, 'Each Run must have a string flowHash.'),
            Assert::string($run['flowRuntimeHash'] ?? null, 'Each Run must have a string flowRuntimeHash.'),
            Assert::string($run['flowType'] ?? null, 'Each Run must have a string flowType.'),
            Assert::datetimeImmutable($run['time'] ?? null, 'Time must be a valid date string.'),
            Assert::nullOrString($run['queueId'] ?? null, 'Each Run must have a null or string queueId.'),
        );
    }

    private static function mapFlowResult(mixed $array, bool $readOnly): FlowResult
    {
        $result = Assert::array($array, 'Each Result must be an array.');

        return new FlowResult(
            Assert::string($result['flowHash'] ?? null, 'Each Result must have a string flowHash.'),
            Assert::string($result['flowRuntimeHash'] ?? null, 'Each Result must have a string flowRuntimeHash.'),
            Assert::string($result['stepSource'] ?? null, 'Each Result must have a string stepSource.'), /** @phpstan-ignore argument.type */
            Assert::string($result['stepHash'] ?? '', 'Each stepHash must have a string or null.'),
            Assert::bool($result['result'] ?? null, 'Each Result must have a bool result.'),
            Assert::datetimeImmutable($result['time'] ?? null, 'Time must be a valid date string.'),
            Assert::string($result['hash'] ?? null, 'Each Result must have a string hash.'),
            $readOnly,
        );
    }

    private static function mapFlowRetry(mixed $array): FlowRetry
    {
        $retry = Assert::array($array, 'Each FlowRetry must be an array.');

        return new FlowRetry(
            Assert::string($retry['flowHash'] ?? null, 'Each FlowRetry must have a string flowHash.'),
            Assert::string($retry['flowRuntimeHash'] ?? null, 'Each FlowRetry must have a string flowRuntimeHash.'),
            Assert::string($retry['stepSource'] ?? null, 'Each FlowRetry must have a string stepSource.'),
            Assert::int($retry['attempt'] ?? null, 'Each FlowRetry must have an integer attempt.'),
            Assert::string($retry['message'] ?? null, 'Each FlowRetry must have a string message.'),
            Assert::datetimeImmutable($retry['time'] ?? null, 'Each FlowRetry must have a valid time.'),
            Assert::string($retry['hash'] ?? null, 'Each FlowRetry must have a string hash.'),
        );
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
        $flowSourceErrors = self::validateClassSource($flowSource, FlowInterface::class, 'flowSource');
        $reasons = [...$reasons, ...$flowSourceErrors];

        if ($flowSourceErrors === []) {
            /** @var class-string<FlowInterface> $flowSource */
            $storedFlowType = $flow['flowType'] ?? null;
            try {
                $liveFlowType = $flowSource::schema()->type();
                if (is_string($storedFlowType) && $storedFlowType !== $liveFlowType) {
                    $reasons[] = sprintf(
                        "flowType '%s' does not match current flowSource local type '%s'",
                        $storedFlowType,
                        $liveFlowType,
                    );
                }
            } catch (Throwable $throwable) {
                $reasons[] = sprintf(
                    "flowSource '%s' schema could not be resolved: %s",
                    $flowSource,
                    $throwable->getMessage(),
                );
            }
        }

        foreach (Assert::array($flowSchemaArray['steps'] ?? [], 'Steps must be an array.') as $stepData) {
            $stepArray = Assert::array($stepData, 'Each step must be an array.');
            $stepSource = $stepArray['source'] ?? null;
            $reasons = [...$reasons, ...self::validateClassSource($stepSource, StepInterface::class, 'Step source')];
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

            $stepSource = $message['stepSource'] ?? null;
            $reasons = [...$reasons, ...self::validateClassSource($stepSource, StepInterface::class, sprintf("Message '%s' step source", self::displayValue($messageSource)))];

            /** @phpstan-ignore-next-line */
            if ($messageHash !== Source::message($messageSource)->messageHash) {
                $reasons[] = sprintf("Message '%s' property-hash mismatch", self::displayValue($messageSource));
            }
        }

        foreach (Assert::array($flow['flowExceptions'] ?? [], 'Exceptions must be an array.') as $excData) {
            $exception = Assert::array($excData, 'Each Exception must be an array.');
            $stepSource = $exception['stepSource'] ?? null;
            $reasons = [...$reasons, ...self::validateClassSource($stepSource, StepInterface::class, 'Exception step source')];
        }

        foreach (Assert::array($flow['flowResults'] ?? [], 'Results must be an array.') as $resData) {
            $result = Assert::array($resData, 'Each Result must be an array.');
            $stepSource = $result['stepSource'] ?? null;
            $reasons = [...$reasons, ...self::validateClassSource($stepSource, StepInterface::class, 'Result step source')];
        }

        foreach (Assert::array($flow['flowRetries'] ?? [], 'Retries must be an array.') as $retryData) {
            $retry = Assert::array($retryData, 'Each Retry must be an array.');
            $stepSource = $retry['stepSource'] ?? null;
            $reasons = [...$reasons, ...self::validateClassSource($stepSource, StepInterface::class, 'Retry step source')];
        }

        return array_unique($reasons);
    }
}
