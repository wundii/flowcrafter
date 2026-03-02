<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use DateTimeImmutable;
use Redis as Client;
use RuntimeException;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Uuid;

class Redis implements StorageInterface
{
    public const PREFIX_TYPE_INSTANCE = 'flow:instance:';

    public const PREFIX_TYPE_MESSAGE = 'flow:message:';

    public const PREFIX_TYPE_RUN = 'flow:run:';

    public const PREFIX_TYPE_SCHEMA = 'flow:schema:';

    private const INDEX_INSTANCE = 'idx:flow';

    private const INDEX_MESSAGE = 'idx:flow:message';

    private const INDEX_RUN = 'idx:flow:run';

    private const INDEX_SCHEMA = 'idx:flow:schema';

    private Client $client;

    public function __construct(string $host, int $port)
    {
        $this->client = new Client();
        $this->client->connect($host, $port);
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
        if (!$this->existIndex(self::INDEX_SCHEMA)) {
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
                'TEXT',
                '$.stubs[*].source',
                'AS',
                'stubSource',
                'TEXT'
            );
        }

        if (!$this->existIndex(self::INDEX_INSTANCE)) {
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
                'TEXT',
                '$.flowSource',
                'AS',
                'flowSource',
                'TEXT',
                '$.flowHash',
                'AS',
                'flowHash',
                'TAG',
                '$.flowSchemaHash',
                'AS',
                'flowSchemaHash',
                'TEXT'
            );
        }

        if (!$this->existIndex(self::INDEX_RUN)) {
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
        }

        if (!$this->existIndex(self::INDEX_MESSAGE)) {
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
                'TEXT',
                '$.messageType',
                'AS',
                'messageType',
                'TEXT',
                '$.messageSource',
                'AS',
                'messageSource',
                'TEXT',
                '$.hash',
                'AS',
                'hash',
                'TAG',
                '$.predecessorHash',
                'AS',
                'predecessorHash',
                'TAG'
            );
        }
    }

    public function registeredFlowSchema(FlowSchema $flowSchema): void
    {
        $key = self::PREFIX_TYPE_SCHEMA . $flowSchema->getHash();
        if ($this->client->exists($key)) {
            return;
        }

        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($flowSchema));
    }

    public function registeredFlow(Flow $flow): void
    {
        $key = self::PREFIX_TYPE_INSTANCE . $flow->getHash();
        if ($this->client->exists($key)) {
            return;
        }

        $data = $flow->jsonSerialize();
        unset($data['flowMessages']);
        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($data));
    }

    public function writeFlow(Flow $flow, ?string $queueId = null): void
    {
        $key = self::PREFIX_TYPE_RUN . $flow->getRuntimeHash();
        $data = [
            'flowHash' => $flow->getHash(),
            'flowRuntimeHash' => $flow->getRuntimeHash(),
            'time' => $flow->getTime()->format(DATE_ATOM),
            'queueId' => $queueId,
        ];
        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($data));
    }

    public function writeFlowMessage(FlowMessage $flowMessage): void
    {
        $key = self::PREFIX_TYPE_MESSAGE . $flowMessage->getHash();
        if ($this->client->exists($key)) {
            return;
        }

        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($flowMessage));
    }

    /**
     * @return array<mixed>
     */
    public function getFlowMessagesByFlowHash(string $flowHash): array
    {
        //tag = @key:{' . $value . '} replace('-', '\-')
        //text = @key:(' . $value . ') replace('-', ' ')
        $flowHash = str_replace('-', '\-', $flowHash);
        $result = $this->client->rawCommand('FT.SEARCH', self::INDEX_MESSAGE, '@flowHash:{' . $flowHash . '}', 'RETURN', '1', '$');
        if ($result === false || !is_array($result)) {
            return [];
        }

        $messages = [];
        $counter = count($result);
        for ($i = 1; $i < $counter; $i += 2) {
            $json = $result[$i + 1][1] ?? null;
            if ($json !== null) {
                $messages[] = json_decode($json, true);
            }
        }

        return $messages;
    }

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     */
    public function addObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, array $message): void
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
}
