<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Exception;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Throwable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class FlowRunner
{
    private ?Flow $flow = null;

    /**
     * @var string[]
     */
    private array $executedStubKey;

    /**
     * @var array<string, Stub[]>
     */
    private array $messageToStubsMap;

    private bool|MessageReturnInterface $messageReturn = false;

    /**
     * @param class-string<FlowInterface> $flowSource
     */
    public function __construct(
        private readonly string $type,
        private readonly string $flowSource,
        private readonly ?string $flowSubject = null,
        private readonly ?StorageInterface $storage = null,
    ) {
        Assert::classString(
            $flowSource,
            FlowInterface::class,
            'The flow must be a class implementing FlowInterface'
        );
    }

    public function getFlow(): ?Flow
    {
        return $this->flow;
    }

    public function run(
        MessageInterface $message,
        ?string $flowHash = null,
        ?string $queueId = null,
    ): bool|MessageReturnInterface {
        $storedFlow = $this->storage?->findFlowByHash((string) $flowHash);

        $flowSchemaHash = $storedFlow instanceof Flow ? $storedFlow->getSchemaHash() : null;

        $this->flow = Flow::create(
            flowType: $this->type,
            flowSource: $this->flowSource,
            flowSchemaHash: $flowSchemaHash,
            flowSubject: $this->flowSubject,
            flowHash: $flowHash,
        );

        if (!$this->flow->isExecutable()) {
            throw new RuntimeException('Flow is not executable, because the flowSchemaHash is different from the stored version');
        }

        $this->flow->addRun($queueId);

        $flowSchema = $this->flow->getSchema();

        $this->storage?->registerFlowSchema($flowSchema);
        $this->storage?->registerFlowInstance($this->flow);
        $this->storage?->appendFlowRun($this->flow, $queueId); #start to run the flow
        $this->executedStubKey = [];
        $this->messageToStubsMap = $flowSchema->getMessageToSubsMap();

        $this->executeStubsRecursive($message);

        return $this->messageReturn ?? false;
    }

    /**
     * @param class-string<StubInterface> $stubSource
     * @param FlowMessage[] $flowMessages
     * @throws Exception
     */
    public function createInstance(string $stubSource, array $flowMessages): StubInterface
    {
        $messages = array_map(
            static fn (FlowMessage $flowMessage): MessageInterface => $flowMessage->getMessage(),
            $flowMessages,
        );

        $containerBuilder = new ContainerBuilder();
        foreach ($messages as $message) {
            $id = get_class($message);

            $definition = new Definition($id);
            $definition->setSynthetic(true);
            $definition->setPublic(true);

            $containerBuilder->setDefinition($id, $definition);
        }

        $containerBuilder->autowire($stubSource)
            ->setPublic(true);

        $containerBuilder->compile();

        foreach ($messages as $message) {
            $containerBuilder->set(get_class($message), $message);
        }

        $stubInstance = $containerBuilder->get($stubSource);
        if (!$stubInstance instanceof StubInterface) {
            throw new RuntimeException('');
        }

        return $stubInstance;
    }

    /**
     * @throws Throwable
     */
    private function executeStubsRecursive(MessageInterface $message, ?string $flowMessageHash = null): void
    {
        $flow = $this->flow;
        if (!$flow instanceof Flow) {
            return;
        }

        $messageClass = get_class($message);

        if (!isset($this->messageToStubsMap[$messageClass])) {
            return;
        }

        foreach ($this->messageToStubsMap[$messageClass] as $stub) {
            $stubSource = $stub->getSource();

            $stubKey = $stubSource . ':' . $messageClass;
            if (in_array($stubKey, $this->executedStubKey, true)) {
                continue;
            }

            $flowMessage = FlowMessage::create(
                flowHash: $flow->getHash(),
                flowRuntimeHash: $flow->getRuntimeHash(),
                stubSource: $stubSource,
                messageTypeEnum: MessageTypeEnum::WAIT,
                predecessorHash: $flowMessageHash,
                message: $message,
            );

            $flow->addMessage($flowMessage);

            $flowMessages = $flow->executableMessages($stubSource);
            if ($flowMessages === []) {
                continue;
            }

            $this->executedStubKey[] = $stubKey;

            try {
                $stubInstance = $this->createInstance($stubSource, $flowMessages);
                $processResult = $stubInstance->process();
            } catch (Throwable $exception) {
                foreach ($flowMessages as $flowMessage) {
                    $flowMessage->setFinish();
                    $this->storage?->appendFlowMessage($flowMessage);
                }

                $flowException = FlowException::create(
                    flowHash: $flow->getHash(),
                    flowRuntimeHash: $flow->getRuntimeHash(),
                    flowType: $flow->getType(),
                    stubSource: $stubSource,
                    code: $exception->getCode(),
                    message: $exception->getMessage(),
                    file: $exception->getFile(),
                    line: $exception->getLine(),
                    traceString: $exception->getTraceAsString(),
                );

                $flow->addException($flowException);
                $this->storage?->appendFlowException($flowException);
                throw $exception;
            }

            foreach ($flowMessages as $flowMessage) {
                $flowMessage->setFinish();
                $this->storage?->appendFlowMessage($flowMessage);
            }

            if (is_object($processResult) && !$processResult instanceof MessageReturnInterface) {
                $this->executeStubsRecursive($processResult, $flowMessage->getHash());
                continue;
            }

            if ($this->messageReturn instanceof MessageReturnInterface) {
                continue;
            }

            $this->messageReturn = $processResult;

            if ($processResult instanceof MessageReturnInterface) {
                $returnFlowMessage = FlowMessage::create(
                    flowHash: $flow->getHash(),
                    flowRuntimeHash: $flow->getRuntimeHash(),
                    stubSource: $stubSource,
                    messageTypeEnum: MessageTypeEnum::FINISH,
                    predecessorHash: $flowMessage->getHash(),
                    message: $processResult,
                );
                $flow->addMessage($returnFlowMessage);
                $this->storage?->appendFlowMessage($returnFlowMessage);
            }
        }
    }
}
