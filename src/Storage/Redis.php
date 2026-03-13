<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Redis as Client;
use RuntimeException;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Converter;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;
use Wundii\Flowcrafter\Stub;
use Wundii\Flowcrafter\Uuid;

class Redis implements StorageInterface
{
    public const PREFIX_TYPE_INSTANCE = 'flow:instance:';

    public const PREFIX_TYPE_MESSAGE = 'flow:message:';

    public const PREFIX_TYPE_EXCEPTION = 'flow:exception:';

    public const PREFIX_TYPE_RUN = 'flow:run:';

    public const PREFIX_TYPE_SCHEMA = 'flow:schema:';

    public const PREFIX_TYPE_SOURCE_STUB = 'flow:source:stub:';

    private const INDEX_INSTANCE = 'idx:flow';

    private const INDEX_MESSAGE = 'idx:flow:message';

    private const INDEX_EXCEPTION = 'idx:flow:exception';

    private const INDEX_RUN = 'idx:flow:run';

    private const INDEX_SCHEMA = 'idx:flow:schema';

    private const INDEX_SOURCE_STUB = 'idx:flow:source:stub';

    protected Client $client;

    public function __construct(string $host, int $port)
    {
        $this->client = new Client();
        $this->client->connect($host, $port);
    }

    public static function escapeValue(string $value): string
    {
        //tag = @key:{' . $value . '} replace('-', '\-')
        //text = @key:(' . $value . ') replace('-', ' ')
        return strtr($value, [
            '\\' => '\\\\',
            '-' => '\-',
            '.' => '\.',
        ]);
    }

    /**
     * @return list<array<mixed, mixed>>
     */
    public static function fetchData(mixed $result): array
    {
        if ($result === false || !is_array($result)) {
            return [];
        }

        $data = [];
        $counter = count($result);
        for ($i = 1; $i < $counter; $i += 2) {
            $json = $result[$i + 1][1] ?? null;
            if ($json !== null) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $data[] = $decoded;
                }
            }
        }

        return $data;
    }

    public function existIndex(string $indexName): bool
    {
        try {
            $this->client->rawCommand('FT.INFO', $indexName);
            return true;
        } catch (\RedisException) {
            return false;
        }
    }

    public function initializeDatabase(): void
    {
        if ($this->existIndex(self::INDEX_SCHEMA)) {
            $this->client->rawCommand('FT.DROPINDEX', self::INDEX_SCHEMA);
        }

        $this->client->rawCommand(
            'FT.CREATE',
            self::INDEX_SCHEMA,
            'ON',
            'JSON',
            'PREFIX',
            '1',
            self::PREFIX_TYPE_SCHEMA,
            'SCHEMA',
            '$.type',
            'AS',
            'type',
            'TAG',
            '$.stubs[*].source',
            'AS',
            'stubSource',
            'TAG'
        );

        $this->client->rawCommand(
            'FT.CREATE',
            self::INDEX_SOURCE_STUB,
            'ON',
            'JSON',
            'PREFIX',
            '1',
            self::PREFIX_TYPE_SOURCE_STUB,
            'SCHEMA',
            '$.stubHash',
            'AS',
            'stubHash',
            'TAG',
            '$.stubSource',
            'AS',
            'stubSource',
            'TAG',
            '$.sourceBase64',
            'AS',
            'stubBase64',
            'TAG'
        );

        if ($this->existIndex(self::INDEX_INSTANCE)) {
            $this->client->rawCommand('FT.DROPINDEX', self::INDEX_INSTANCE);
        }

        $this->client->rawCommand(
            'FT.CREATE',
            self::INDEX_INSTANCE,
            'ON',
            'JSON',
            'PREFIX',
            '1',
            self::PREFIX_TYPE_INSTANCE,
            'SCHEMA',
            '$.flowType',
            'AS',
            'flowType',
            'TAG',
            '$.flowSource',
            'AS',
            'flowSource',
            'TAG',
            '$.flowHash',
            'AS',
            'flowHash',
            'TAG',
            'SEPARATOR',
            '|',
            'SORTABLE',
            '$.flowSchemaHash',
            'AS',
            'flowSchemaHash',
            'TAG',
            '$.time',
            'AS',
            'time',
            'NUMERIC',
            'SORTABLE',
        );

        if ($this->existIndex(self::INDEX_RUN)) {
            $this->client->rawCommand('FT.DROPINDEX', self::INDEX_RUN);
        }

        $this->client->rawCommand(
            'FT.CREATE',
            self::INDEX_RUN,
            'ON',
            'JSON',
            'PREFIX',
            '1',
            self::PREFIX_TYPE_RUN,
            'SCHEMA',
            '$.flowHash',
            'AS',
            'flowHash',
            'TAG',
            '$.flowRuntimeHash',
            'AS',
            'flowRuntimeHash',
            'TAG',
        );

        if ($this->existIndex(self::INDEX_MESSAGE)) {
            $this->client->rawCommand('FT.DROPINDEX', self::INDEX_MESSAGE);
        }

        $this->client->rawCommand(
            'FT.CREATE',
            self::INDEX_MESSAGE,
            'ON',
            'JSON',
            'PREFIX',
            '1',
            self::PREFIX_TYPE_MESSAGE,
            'SCHEMA',
            '$.flowHash',
            'AS',
            'flowHash',
            'TAG',
            '$.flowRuntimeHash',
            'AS',
            'flowRuntimeHash',
            'TAG',
            '$.stubSource',
            'AS',
            'stubSource',
            'TAG',
            '$.stubHash',
            'AS',
            'stubHash',
            'TAG',
            '$.messageType',
            'AS',
            'messageType',
            'TAG',
            '$.messageSource',
            'AS',
            'messageSource',
            'TAG',
            '$.hash',
            'AS',
            'hash',
            'TAG',
            '$.predecessorHash',
            'AS',
            'predecessorHash',
            'TAG'
        );

        if ($this->existIndex(self::INDEX_EXCEPTION)) {
            $this->client->rawCommand('FT.DROPINDEX', self::INDEX_EXCEPTION);
        }

        $this->client->rawCommand(
            'FT.CREATE',
            self::INDEX_EXCEPTION,
            'ON',
            'JSON',
            'PREFIX',
            '1',
            self::PREFIX_TYPE_EXCEPTION,
            'SCHEMA',
            '$.flowHash',
            'AS',
            'flowHash',
            'TAG',
            '$.flowRuntimeHash',
            'AS',
            'flowRuntimeHash',
            'TAG',
            '$.flowType',
            'AS',
            'flowType',
            'TAG',
            '$.stubSource',
            'AS',
            'stubSource',
            'TAG',
            '$.stubHash',
            'AS',
            'stubHash',
            'TAG',
            '$.code',
            'AS',
            'code',
            'NUMERIC',
            '$.message',
            'AS',
            'message',
            'TAG',
            '$.file',
            'AS',
            'file',
            'TAG',
            '$.line',
            'AS',
            'line',
            'NUMERIC',
            '$.traceString',
            'AS',
            'traceString',
            'TEXT',
            '$.time',
            'AS',
            'time',
            'NUMERIC',
            'SORTABLE',
            '$.hash',
            'AS',
            'hash',
            'TAG',
            'SORTABLE',
        );
    }

    public function registerFlowSchema(FlowSchema $flowSchema): void
    {
        $key = self::PREFIX_TYPE_SCHEMA . $flowSchema->getHash();
        if ($this->client->exists($key)) {
            return;
        }

        $type = $flowSchema->type();
        $type = self::escapeValue($type);

        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_SCHEMA, '@type:{' . $type . '}');

        $events = self::fetchData($result);

        if ($events !== []) {
            throw new InvalidArgumentException('The flow type "' . $flowSchema->type() . '" already exists.');
        }

        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($flowSchema));
    }

    /**
     * @param class-string $stubSource
     */
    public function registerStubSource(string $stubSource): string
    {
        $stubSource = Stub::source($stubSource);

        $key = self::PREFIX_TYPE_SOURCE_STUB . $stubSource['stubHash'];
        if ($this->client->exists($key)) {
            return $stubSource['stubHash'];
        }

        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($stubSource));

        return $stubSource['stubHash'];
    }

    public function registerFlowInstance(Flow $flow): void
    {
        $key = self::PREFIX_TYPE_INSTANCE . $flow->getHash();
        if ($this->client->exists($key)) {
            return;
        }

        $data = $flow->jsonSerialize();
        unset($data['flowSchema']);
        unset($data['flowMessages']);
        unset($data['flowExceptions']);
        unset($data['flowRuns']);
        unset($data['isExecutable']);
        $data['time'] = $flow->getTime()->getTimestamp();

        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($data));
    }

    public function appendFlowRun(Flow $flow, ?string $queueId = null): void
    {
        $key = self::PREFIX_TYPE_RUN . $flow->getRuntimeHash();
        $data = [
            'flowHash' => $flow->getHash(),
            'flowRuntimeHash' => $flow->getRuntimeHash(),
            'time' => $flow->getTime()->format(DateTimeInterface::RFC3339_EXTENDED),
            'queueId' => $queueId,
        ];

        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($data));
    }

    public function appendFlowMessage(FlowMessage $flowMessage): void
    {
        $key = self::PREFIX_TYPE_MESSAGE . $flowMessage->getHash();
        if ($this->client->exists($key)) {
            return;
        }

        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($flowMessage));
    }

    public function appendFlowException(FlowException $flowException): void
    {
        $key = self::PREFIX_TYPE_EXCEPTION . $flowException->getHash();
        if ($this->client->exists($key)) {
            return;
        }

        $data = $flowException->jsonSerialize();
        $data['time'] = $flowException->getTime()->getTimestamp();

        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($data));
    }

    public function openQueues(): int
    {
        $len = $this->client->lLen('flow:queue');
        return $len === false ? 0 : (int) $len;
    }

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     */
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, array $message): void
    {
        Assert::classString($flowSource, FlowInterface::class);
        Assert::classString($messageSource, MessageInterface::class);

        $data = [
            'type' => $type,
            'flowSource' => $flowSource,
            'flowHash' => $flowHash,
            'messageSource' => $messageSource,
            'message' => $message,
        ];

        $this->client->lPush('flow:queue', json_encode($data));
    }

    /**
     * @return iterable<ObserveItem>
     */
    public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable
    {
        $startExecutionTime = microtime(true);

        while (true) {
            if ($maxExecutionTimeInSeconds > 0.0 && (microtime(true) - $startExecutionTime) >= $maxExecutionTimeInSeconds) {
                break;
            }

            $result = $this->client->brPop('flow:queue', 1);
            if (!is_array($result)) {
                continue;
            }

            if (!array_key_exists(1, $result)) {
                continue;
            }

            $payload = json_decode($result[1], true);
            if (!is_array($payload)) {
                throw new RuntimeException('The flow message payload must be a valid JSON object.');
            }

            yield new ObserveItem(
                queueId: Uuid::uuid7(new DateTimeImmutable())->toString(),
                /** @phpstan-ignore-next-line */
                type: $payload['type'] ?? '',
                flowSource: $payload['flowSource'] ?? '',
                flowHash: $payload['flowHash'] ?? null,
                messageSource: $payload['messageSource'] ?? '',
                message: $payload['message'] ?? [],
            );
        }
    }

    /**
     * @return iterable<array<mixed>>
     */
    public function findAllSchemas(): iterable
    {
        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_SCHEMA, '*');

        $events = self::fetchData($result);

        foreach ($events as $event) {
            yield $event;
        }
    }

    /**
     * @return iterable<ObserveItem>
     */
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable
    {
        $items = $this->client->lRange('flow:queue', 0, -1);
        if ($items === false || $items === []) {
            return;
        }

        if ($sortEnum === SortEnum::ASC) {
            $items = array_reverse($items);
        }

        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }

            $payload = json_decode($item, true);
            if (!is_array($payload)) {
                continue;
            }

            yield new ObserveItem(
                /** @phpstan-ignore-next-line */
                queueId: $payload['queueId'] ?? '',
                type: $payload['type'] ?? '',
                flowSource: $payload['flowSource'] ?? '',
                flowHash: $payload['flowHash'] ?? null,
                messageSource: $payload['messageSource'] ?? '',
                message: $payload['message'] ?? [],
            );
        }
    }

    public function countFlows(): int
    {
        return $this->countFlowsBySource('*');
    }

    public function countFlowsBySource(string $flowSource = ''): int
    {
        $flowSource = self::escapeValue($flowSource);
        $value = match ($flowSource) {
            '*', '' => '*',
            default => '@flowSource:{' . $flowSource . '}',
        };
        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_INSTANCE, $value, 'LIMIT', '0', '0');

        return is_array($result) && is_int($result[0] ?? null) ? $result[0] : 0;
    }

    public function countFlowsByType(string $flowType = ''): int
    {
        $flowType = self::escapeValue($flowType);
        $value = match ($flowType) {
            '*', '' => '*',
            default => '@flowType:{' . $flowType . '\.v*}',
        };
        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_INSTANCE, $value, 'LIMIT', '0', '0');

        return is_array($result) && is_int($result[0] ?? null) ? $result[0] : 0;
    }

    public function countExceptions(): int
    {
        return $this->countExceptionsByFlowHash('*');
    }

    public function countExceptionsByFlowHash(string $flowHash = ''): int
    {
        $flowHash = self::escapeValue($flowHash);
        $value = match ($flowHash) {
            '*', '' => '*',
            default => '@flowHash:{' . $flowHash . '}',
        };
        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_EXCEPTION, $value, 'LIMIT', '0', '0');

        return is_array($result) && is_int($result[0] ?? null) ? $result[0] : 0;
    }

    public function findAllFlows(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        return $this->findFlowsBySource('*', $sortEnum, $top, $skip, $from, $to);
    }

    /**
     * @return FlowEntity[]
     * @throws Exception
     */
    public function findFlowsBySource(string $flowSource, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        $flowSource = self::escapeValue($flowSource);
        $skip = max(0, $skip);
        $top = max(1, $top);

        $value = match ($flowSource) {
            '*', '' => '*',
            default => '@flowSource:{' . $flowSource . '}',
        };

        $args = ['FT.SEARCH', self::INDEX_INSTANCE, $value, 'SORTBY', 'flowHash', $sortEnum->name, 'LIMIT', $skip, $top];
        if ($from instanceof DateTimeInterface || $to instanceof DateTimeInterface) {
            $args[] = 'FILTER';
            $args[] = 'time';
            $args[] = $from instanceof DateTimeInterface ? (string) $from->getTimestamp() : '-inf';
            $args[] = $to instanceof DateTimeInterface ? (string) $to->getTimestamp() : '+inf';
        }

        $args[] = 'RETURN';
        $args[] = '1';
        $args[] = '$';

        $result = $this->client->rawcommand(...$args);
        $events = self::fetchData($result);

        if ($events === []) {
            return [];
        }

        foreach ($events as $event) {
            /** @var string $flowHash */
            $flowHash = $event['flowHash'] ?? '';
            yield new FlowEntity(
                flowHash: $flowHash,
                flowType: $event['flowType'] ?? '',
                flowSource: $event['flowSource'] ?? '',
                flowSubject: $event['flowSubject'] ?? '',
                time: (new DateTimeImmutable())->setTimestamp((int) ($event['time'] ?? 0)),
                exceptionCount: $this->countExceptionsByFlowHash($flowHash),
            );
        }
    }

    /**
     * @return FlowEntity[]
     * @throws Exception
     */
    public function findFlowsByType(string $flowType, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        $flowType = self::escapeValue($flowType);
        $skip = max(0, $skip);
        $top = max(1, $top);

        $value = match ($flowType) {
            '*', '' => '*',
            default => '@flowType:{' . $flowType . '\.v*}',
        };

        $args = ['FT.SEARCH', self::INDEX_INSTANCE, $value, 'SORTBY', 'flowHash', $sortEnum->name, 'LIMIT', $skip, $top];
        if ($from instanceof DateTimeInterface || $to instanceof DateTimeInterface) {
            $args[] = 'FILTER';
            $args[] = 'time';
            $args[] = $from instanceof DateTimeInterface ? (string) $from->getTimestamp() : '-inf';
            $args[] = $to instanceof DateTimeInterface ? (string) $to->getTimestamp() : '+inf';
        }

        $args[] = 'RETURN';
        $args[] = '1';
        $args[] = '$';

        $result = $this->client->rawcommand(...$args);
        $events = self::fetchData($result);

        if ($events === []) {
            return [];
        }

        foreach ($events as $event) {
            /** @var string $flowHash */
            $flowHash = $event['flowHash'] ?? '';
            yield new FlowEntity(
                flowHash: $flowHash,
                flowType: $event['flowType'] ?? '',
                flowSource: $event['flowSource'] ?? '',
                flowSubject: $event['flowSubject'] ?? '',
                time: (new DateTimeImmutable())->setTimestamp((int) ($event['time'] ?? 0)),
                exceptionCount: $this->countExceptionsByFlowHash($flowHash),
            );
        }
    }

    /**
     * @return FlowException[]
     * @throws Exception
     */
    public function findAllExceptions(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        return $this->findExceptionsByFlowHash('*', $sortEnum, $top, $skip, $from, $to);
    }

    /**
     * @return FlowException[]
     * @throws Exception
     */
    public function findExceptionsByFlowHash(string $flowHash, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        $flowHash = self::escapeValue($flowHash);
        $skip = max(0, $skip);
        $top = max(1, $top);

        $value = match ($flowHash) {
            '*', '' => '*',
            default => '@flowHash:{' . $flowHash . '}',
        };

        $args = ['FT.SEARCH', self::INDEX_EXCEPTION, $value, 'SORTBY', 'hash', $sortEnum->name, 'LIMIT', $skip, $top];
        if ($from instanceof DateTimeInterface || $to instanceof DateTimeInterface) {
            $args[] = 'FILTER';
            $args[] = 'time';
            $args[] = $from instanceof DateTimeInterface ? (string) $from->getTimestamp() : '-inf';
            $args[] = $to instanceof DateTimeInterface ? (string) $to->getTimestamp() : '+inf';
        }

        $args[] = 'RETURN';
        $args[] = '1';
        $args[] = '$';

        $result = $this->client->rawcommand(...$args);
        $events = self::fetchData($result);

        if ($events === []) {
            return [];
        }

        foreach ($events as $event) {
            yield new FlowException(
                /** @phpstan-ignore-next-line */
                flowHash: $event['flowHash'] ?? '',
                flowRuntimeHash: $event['flowRuntimeHash'] ?? '',
                flowType: $event['flowType'] ?? '',
                stubSource: $event['stubSource'] ?? '',
                stubHash: $event['stubHash'] ?? null,
                code: $event['code'] ?? 0,
                message: $event['message'] ?? '',
                file: $event['file'] ?? '',
                line: $event['line'] ?? 0,
                traceString: $event['traceString'] ?? '',
                time: (new DateTimeImmutable())->setTimestamp((int) ($event['time'] ?? 0)),
                hash: $event['hash'] ?? '',
            );
        }
    }

    public function findFlowByHash(string $flowHash): ?Flow
    {
        if ($flowHash === '' || $flowHash === '*') {
            return null;
        }

        $flowHash = self::escapeValue($flowHash);

        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_INSTANCE, '@flowHash:{' . $flowHash . '}', 'RETURN', '1', '$');
        $flowArray = self::fetchData($result)[0] ?? [];

        if ($flowArray === []) {
            return null;
        }

        $schemaHash = is_string($flowArray['flowSchemaHash'] ?? null) ? $flowArray['flowSchemaHash'] : '';
        $flowArray['flowSchema'] = $this->findSchemaByHash($schemaHash);
        $flowArray['time'] = $this->timestampToRFC3339Extended($flowArray['time'] ?? 0);

        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_MESSAGE, '@flowHash:{' . $flowHash . '}', 'LIMIT', '0', '10000', 'RETURN', '1', '$');
        foreach (self::fetchData($result) as $messageEvent) {
            $flowArray['flowMessages'][] = $messageEvent;
        }

        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_EXCEPTION, '@flowHash:{' . $flowHash . '}', 'LIMIT', '0', '10000', 'RETURN', '1', '$');
        foreach (self::fetchData($result) as $exceptionEvent) {
            $exceptionEvent['time'] = $this->timestampToRFC3339Extended($exceptionEvent['time'] ?? 0);
            $flowArray['flowExceptions'][] = $exceptionEvent;
        }

        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_RUN, '@flowHash:{' . $flowHash . '}', 'LIMIT', '0', '10000', 'RETURN', '1', '$');
        foreach (self::fetchData($result) as $runEvent) {
            $flowArray['flowRuns'][] = $runEvent;
        }

        return Converter::arrayToFlow($flowArray);
    }

    public function findFlowByRuntimeHash(string $flowRuntimeHash): ?Flow
    {
        $flowRuntimeHash = self::escapeValue($flowRuntimeHash);
        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_RUN, '@flowRuntimeHash:{' . $flowRuntimeHash . '}', 'RETURN', '1', '$');
        $flowHash = self::fetchData($result)[0]['flowHash'] ?? '';
        if (!is_string($flowHash)) {
            $flowHash = '';
        }

        return $this->findFlowByHash($flowHash);
    }

    /**
     * @return list<array<mixed, mixed>>
     */
    private function findSchemaByHash(string $hash): array
    {
        $key = self::PREFIX_TYPE_SCHEMA . $hash;
        if (!$this->client->exists($key)) {
            throw new RuntimeException('Schema ' . $hash . ' does not exist.');
        }

        $result = $this->client->rawCommand('JSON.GET', $key, '$');
        if (!is_string($result)) {
            throw new RuntimeException('Schema ' . $hash . ' does not return a valid JSON.');
        }

        $flowSchema = json_decode($result, true);
        if (!is_array($flowSchema)) {
            throw new RuntimeException('Schema ' . $hash . ' does not return a valid JSON.');
        }

        /** @var list<array<mixed>> $firstEntry */
        $firstEntry = $flowSchema[0] ?? [];

        return $firstEntry;
    }

    private function timestampToRFC3339Extended(mixed $timestamp): string
    {
        return (new DateTimeImmutable())->setTimestamp((int) (is_numeric($timestamp) ? $timestamp : 0))->format(DateTimeInterface::RFC3339_EXTENDED);
    }
}
