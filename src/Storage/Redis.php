<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use Redis as Client;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\ObserveItem;

class Redis implements StorageInterface
{
    private const PREFIX_SCHEMA = 'flow:schema:';

    private const PREFIX_FLOW = 'flow:instance:';

    private const PREFIX_FLOW_RUN = 'flow:run:';

    private const PREFIX_MESSAGE = 'flow:message:';

    private const INDEX_SCHEMA = 'idx:flow:schema';

    private const INDEX_FLOW = 'idx:flow';

    private const INDEX_FLOW_RUN = 'idx:flow:run';

    private const INDEX_MESSAGE = 'idx:flow:message';

    private Client $client;

    public function __construct(string $host, int $port)
    {
        $this->client = new Client();
        $this->client->connect($host, $port);
    }

    public function initializeDatabase(): void
    {
        $this->client->rawCommand(
            'FT.CREATE',
            self::INDEX_SCHEMA,
            'ON',
            'JSON',
            'PREFIX',
            '1',
            self::PREFIX_SCHEMA,
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

        $this->client->rawCommand(
            'FT.CREATE',
            self::INDEX_FLOW,
            'ON',
            'JSON',
            'PREFIX',
            '1',
            self::PREFIX_FLOW,
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

        $this->client->rawCommand(
            'FT.CREATE',
            self::INDEX_FLOW_RUN,
            'ON',
            'JSON',
            'PREFIX',
            '1',
            self::PREFIX_FLOW_RUN,
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

        $this->client->rawCommand(
            'FT.CREATE',
            self::INDEX_MESSAGE,
            'ON',
            'JSON',
            'PREFIX',
            '1',
            self::PREFIX_MESSAGE,
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

    public function registeredFlowSchema(FlowSchema $flowSchema): void
    {
        $key = self::PREFIX_SCHEMA . $flowSchema->getHash();
        if ($this->client->exists($key)) {
            return;
        }

        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($flowSchema));
    }

    public function registeredFlow(Flow $flow): void
    {
        $key = self::PREFIX_FLOW . $flow->getHash();
        if ($this->client->exists($key)) {
            return;
        }

        $data = $flow->jsonSerialize();
        unset($data['flowMessages']);
        $this->client->rawCommand('JSON.SET', $key, '$', json_encode($data));
    }

    public function writeFlow(Flow $flow, ?string $queueId = null): void
    {
        $key = self::PREFIX_FLOW_RUN . $flow->getRuntimeHash();
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
        $key = self::PREFIX_MESSAGE . $flowMessage->getHash();
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
     * @return iterable<ObserveItem>
     */
    public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable
    {
        /** @phpstan-ignore argument.type */
        yield new ObserveItem('1', '', '', '', '', []);
    }
}
