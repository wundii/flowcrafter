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
use Wundii\Flowcrafter\FlowResult;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Storage\Config\RedisConfig;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;
use Wundii\Flowcrafter\Uuid;

class Redis extends Service implements StorageInterface
{
    public const PREFIX_TYPE_INSTANCE = 'flow:instance:';

    public const PREFIX_TYPE_MESSAGE = 'flow:message:';

    public const PREFIX_TYPE_EXCEPTION = 'flow:exception:';

    public const PREFIX_TYPE_RESULT = 'flow:result:';

    public const PREFIX_TYPE_RUN = 'flow:run:';

    public const PREFIX_TYPE_SCHEMA = 'flow:schema:';

    public const PREFIX_TYPE_SOURCE_STUB = 'flow:source:stub:';

    private const INDEX_INSTANCE = 'idx:flow';

    private const INDEX_MESSAGE = 'idx:flow:message';

    private const INDEX_EXCEPTION = 'idx:flow:exception';

    private const INDEX_RESULT = 'idx:flow:result';

    private const INDEX_RUN = 'idx:flow:run';

    private const INDEX_SCHEMA = 'idx:flow:schema';

    private const INDEX_SOURCE_STUB = 'idx:flow:source:stub';

    protected Client $client;

    public function __construct(RedisConfig $redisConfig, string $sqliteFile)
    {
        parent::__construct($sqliteFile);

        $this->client = new Client();
        $this->client->connect($redisConfig->getHost(), $redisConfig->getPort());
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
            /** @var array{1: string}|null $entry */
            $entry = $result[$i + 1] ?? null;
            $json = $entry[1] ?? null;
            if (is_string($json)) {
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
        parent::initializeDatabase();

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
            'TAG',
        );

        if ($this->existIndex(self::INDEX_SOURCE_STUB)) {
            $this->client->rawCommand('FT.DROPINDEX', self::INDEX_SOURCE_STUB);
        }

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
            '$.sourceContent',
            'AS',
            'sourceContent',
            'TAG',
            '$.time',
            'AS',
            'time',
            'NUMERIC',
            'SORTABLE',
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
            '$.flowSubject',
            'AS',
            'flowSubject',
            'TAG',
            'SEPARATOR',
            '|',
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
            '$.flowType',
            'AS',
            'flowType',
            'TAG',
            '$.time',
            'AS',
            'time',
            'NUMERIC',
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
            'TAG',
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

        if ($this->existIndex(self::INDEX_RESULT)) {
            $this->client->rawCommand('FT.DROPINDEX', self::INDEX_RESULT);
        }

        $this->client->rawCommand(
            'FT.CREATE',
            self::INDEX_RESULT,
            'ON',
            'JSON',
            'PREFIX',
            '1',
            self::PREFIX_TYPE_RESULT,
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
            '$.result',
            'AS',
            'result',
            'NUMERIC',
            '$.time',
            'AS',
            'time',
            'NUMERIC',
            'SORTABLE',
            '$.hash',
            'AS',
            'hash',
            'TAG',
        );
    }

    public function saveFlow(Flow $flow): void
    {
        parent::saveFlow($flow);
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

    public function registerStubSource(StubSourceEntity $stubSourceEntity): void
    {
        $key = self::PREFIX_TYPE_SOURCE_STUB . $stubSourceEntity->stubHash;
        if ($this->client->exists($key)) {
            return;
        }

        $data = $stubSourceEntity->jsonSerialize();
        $data['time'] = $stubSourceEntity->time->getTimestamp();

        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($data));
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
        unset($data['flowResults']);
        unset($data['flowRuns']);
        unset($data['flowStatus']);
        unset($data['isExecutable']);
        unset($data['isReadOnly']);
        $data['time'] = $flow->getTime()->getTimestamp();

        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($data));
    }

    public function appendFlowRun(Flow $flow, ?string $queueId = null): void
    {
        $key = self::PREFIX_TYPE_RUN . $flow->getRuntimeHash();
        $data = [
            'flowHash' => $flow->getHash(),
            'flowRuntimeHash' => $flow->getRuntimeHash(),
            'flowType' => $flow->getType(),
            'time' => $flow->getTime()->getTimestamp(),
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

    public function appendFlowResult(FlowResult $flowResult): void
    {
        $key = self::PREFIX_TYPE_RESULT . $flowResult->getHash();
        if ($this->client->exists($key)) {
            return;
        }

        $data = $flowResult->jsonSerialize();
        $data['time'] = $flowResult->getTime()->getTimestamp();
        $data['result'] = (int) $flowResult->getResult();

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
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, array $message, array $includeStubs = [], ?string $flowSubject = null): void
    {
        Assert::classString($flowSource, FlowInterface::class);
        Assert::classString($messageSource, MessageInterface::class);

        $data = [
            'type' => $type,
            'flowSource' => $flowSource,
            'flowHash' => $flowHash,
            'messageSource' => $messageSource,
            'message' => $message,
            'includeStubs' => $includeStubs,
            'flowSubject' => $flowSubject,
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

            /** @var array{type: string, flowSource: class-string<FlowInterface>, flowHash: ?string, messageSource: string, message: array<mixed>, includeStubs?: class-string[], flowSubject?: ?string} $payload */
            yield new ObserveItem(
                queueId: Uuid::uuid7(new DateTimeImmutable())->toString(),
                type: $payload['type'],
                flowSubject: $payload['flowSubject'] ?? null,
                flowSource: $payload['flowSource'],
                flowHash: $payload['flowHash'],
                messageSource: $payload['messageSource'],
                message: $payload['message'],
                includeStubs: $payload['includeStubs'] ?? [],
            );
        }
    }

    /**
     * @return iterable<string>
     */
    public function findAllFlowHashes(): iterable
    {
        $iterator = null;
        do {
            $keys = $this->client->scan($iterator, self::PREFIX_TYPE_INSTANCE . '*', 1000);
            foreach (is_array($keys) ? $keys : [] as $key) {
                yield substr((string) $key, strlen(self::PREFIX_TYPE_INSTANCE));
            }
        } while ((int) $iterator !== 0);
    }

    /**
     * @return iterable<array<mixed>>
     */
    public function findAllSchemas(): iterable
    {
        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_SCHEMA, '*');
        foreach (self::fetchData($result) as $event) {
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

            /** @var array{queueId: string, type: string, flowSource: class-string<FlowInterface>, flowHash: ?string, messageSource: string, message: array<mixed>, includeStubs?: class-string[], flowSubject?: ?string} $payload */
            yield new ObserveItem(
                queueId: $payload['queueId'],
                type: $payload['type'],
                flowSubject: $payload['flowSubject'] ?? null,
                flowSource: $payload['flowSource'],
                flowHash: $payload['flowHash'],
                messageSource: $payload['messageSource'],
                message: $payload['message'],
                includeStubs: $payload['includeStubs'] ?? [],
            );
        }
    }

    public function countExceptions(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int
    {
        return $this->countExceptionsByFlowHash('*', $from, $to);
    }

    public function countExceptionsByFlowHash(string $flowHash = '', ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int
    {
        $flowHash = self::escapeValue($flowHash);
        $value = match ($flowHash) {
            '*', '' => '*',
            default => '@flowHash:{' . $flowHash . '}',
        };

        $args = ['FT.SEARCH', self::INDEX_EXCEPTION, $value, 'LIMIT', '0', '0'];
        if ($from instanceof DateTimeInterface || $to instanceof DateTimeInterface) {
            $args[] = 'FILTER';
            $args[] = 'time';
            $args[] = $from instanceof DateTimeInterface ? (string) $from->getTimestamp() : '-inf';
            $args[] = $to instanceof DateTimeInterface ? (string) $to->getTimestamp() : '+inf';
        }

        $result = $this->client->rawCommand(...$args);

        return is_array($result) && is_int($result[0] ?? null) ? $result[0] : 0;
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

        foreach (self::fetchData($result) as $event) {
            /** @var array{flowHash: string, flowRuntimeHash: string, flowType: string, stubSource: class-string<StubInterface>, stubHash: string, code: int, message: string, file: string, line: int, traceString: string, time: int, hash: string} $event */
            yield new FlowException(
                flowHash: $event['flowHash'],
                flowRuntimeHash: $event['flowRuntimeHash'],
                flowType: $event['flowType'],
                stubSource: $event['stubSource'],
                stubHash: $event['stubHash'],
                code: $event['code'],
                message: $event['message'],
                file: $event['file'],
                line: $event['line'],
                traceString: $event['traceString'],
                time: (new DateTimeImmutable())->setTimestamp($event['time']),
                hash: $event['hash'],
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

        /** @var array<string, mixed> $flowArray */
        $schemaHash = is_string($flowArray['flowSchemaHash'] ?? null) ? $flowArray['flowSchemaHash'] : '';
        $flowArray['flowSchema'] = $this->findSchemaByHash($schemaHash);
        $flowArray['time'] = $this->timestampToRFC3339Extended($flowArray['time'] ?? 0);

        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_MESSAGE, '@flowHash:{' . $flowHash . '}', 'LIMIT', '0', '10000', 'RETURN', '1', '$');
        $flowMessages = self::fetchData($result);

        $flowArray['flowMessages'] = $flowMessages;

        $flowExceptions = [];
        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_EXCEPTION, '@flowHash:{' . $flowHash . '}', 'SORTBY', 'time', 'ASC', 'LIMIT', '0', '10000', 'RETURN', '1', '$');
        foreach (self::fetchData($result) as $exceptionEvent) {
            $exceptionEvent['time'] = $this->timestampToRFC3339Extended($exceptionEvent['time'] ?? 0);
            $flowExceptions[] = $exceptionEvent;
        }

        $flowArray['flowExceptions'] = $flowExceptions;

        $flowResults = [];
        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_RESULT, '@flowHash:{' . $flowHash . '}', 'SORTBY', 'time', 'ASC', 'LIMIT', '0', '10000', 'RETURN', '1', '$');
        foreach (self::fetchData($result) as $resultEvent) {
            $resultEvent['time'] = $this->timestampToRFC3339Extended($resultEvent['time'] ?? 0);
            $resultEvent['result'] = (bool) ($resultEvent['result'] ?? false);
            $flowResults[] = $resultEvent;
        }

        $flowArray['flowResults'] = $flowResults;

        $flowRuns = [];
        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_RUN, '@flowHash:{' . $flowHash . '}', 'SORTBY', 'time', 'ASC', 'LIMIT', '0', '10000', 'RETURN', '1', '$');
        foreach (self::fetchData($result) as $runEvent) {
            $runEvent['time'] = $this->timestampToRFC3339Extended($runEvent['time'] ?? 0);
            $flowRuns[] = $runEvent;
        }

        $flowArray['flowRuns'] = $flowRuns;

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

    public function findStubSourceByHash(string $stubHash): ?StubSourceEntity
    {
        if ($stubHash === '' || $stubHash === '*') {
            return null;
        }

        $stubHash = self::escapeValue($stubHash);
        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_SOURCE_STUB, '@stubHash:{' . $stubHash . '}', 'RETURN', '1', '$');
        $stubSourceArray = self::fetchData($result)[0] ?? [];

        if ($stubSourceArray === []) {
            return null;
        }

        /** @var array{stubHash: string, stubSource: class-string<StubInterface>, sourceContent: string, time: int} $stubSourceArray */
        return new StubSourceEntity(
            stubHash: $stubSourceArray['stubHash'],
            stubSource: $stubSourceArray['stubSource'],
            sourceContent: $stubSourceArray['sourceContent'],
            time: (new DateTimeImmutable())->setTimestamp($stubSourceArray['time']),
        );
    }

    /**
     * @param class-string $stubSource
     * @return iterable<StubSourceEntity>
     */
    public function findStubSourcesByStubSource(string $stubSource): iterable
    {
        $stubSource = self::escapeValue($stubSource);

        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_SOURCE_STUB, '@stubSource:{' . $stubSource . '}', 'RETURN', '1', '$');
        foreach (self::fetchData($result) as $stubSourceEvent) {
            /** @var array{stubHash: string, stubSource: class-string<StubInterface>, sourceContent: string, time: int} $stubSourceEvent */
            yield new StubSourceEntity(
                stubHash: $stubSourceEvent['stubHash'],
                stubSource: $stubSourceEvent['stubSource'],
                sourceContent: $stubSourceEvent['sourceContent'],
                time: (new DateTimeImmutable())->setTimestamp($stubSourceEvent['time']),
            );
        }
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
