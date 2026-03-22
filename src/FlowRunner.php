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

    /**
     * @var class-string[]
     */
    private array $includeStubs = [];

    private bool|MessageReturnInterface $messageReturn = false;

    /**
     * @param class-string<FlowInterface> $flowSource
     * @param array<class-string|object> $dependenciesInjection
     */
    public function __construct(
        private readonly string $type,
        private readonly string $flowSource,
        private readonly ?string $flowSubject = null,
        private readonly ?StorageInterface $storage = null,
        private readonly array $dependenciesInjection = [],
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

    /**
     * @param class-string[] $includeStubs
     */
    public function run(
        MessageInterface $message,
        ?string $flowHash = null,
        ?string $queueId = null,
        array $includeStubs = [],
    ): bool|MessageReturnInterface {
        $storedFlow = $this->storage?->findFlowByHash((string) $flowHash);

        $flowSchemaHash = $storedFlow instanceof Flow ? $storedFlow->getSchemaHash() : null;
        $flowSubject = $storedFlow instanceof Flow ? $storedFlow->getSubject() : $this->flowSubject;

        $this->flow = Flow::create(
            flowType: $this->type,
            flowSource: $this->flowSource,
            flowSchemaHash: $flowSchemaHash,
            flowSubject: $flowSubject,
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
        $this->includeStubs = $includeStubs;
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
            $className = get_class($message);

            $definition = new Definition($className);
            $definition->setSynthetic(true);
            $definition->setPublic(true);

            $containerBuilder->setDefinition($className, $definition);
        }

        foreach ($this->dependenciesInjection as $dependencyInjection) {
            $className = is_object($dependencyInjection)
                ? get_class($dependencyInjection)
                : $dependencyInjection;

            $definition = new Definition($className);
            $definition->setSynthetic(is_object($dependencyInjection));
            $definition->setPublic(true);

            $containerBuilder->setDefinition($className, $definition);
        }

        $containerBuilder->autowire($stubSource)
            ->setPublic(true);

        $containerBuilder->compile();

        foreach ($messages as $message) {
            $containerBuilder->set(get_class($message), $message);
        }

        foreach ($this->dependenciesInjection as $dependencyInjection) {
            if (!is_object($dependencyInjection)) {
                continue;
            }

            $containerBuilder->set(get_class($dependencyInjection), $dependencyInjection);
        }

        $stubInstance = $containerBuilder->get($stubSource);
        if (!$stubInstance instanceof StubInterface) {
            throw new RuntimeException('Stub instance must implement StubInterface.');
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

            if ($this->includeStubs !== [] && !in_array($stubSource, $this->includeStubs, true)) {
                continue;
            }

            $stubKey = $stubSource . ':' . $messageClass;
            if (in_array($stubKey, $this->executedStubKey, true)) {
                continue;
            }

            $flowMessage = FlowMessage::create(
                flowHash: $flow->getHash(),
                flowRuntimeHash: $flow->getRuntimeHash(),
                stubSource: $stubSource,
                stubHash: null,
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

            $stubHash = $this->storage?->registerStubSource($stubSource);

            try {
                $stubInstance = $this->createInstance($stubSource, $flowMessages);
                $processResult = $stubInstance->process();
            } catch (Throwable $exception) {
                foreach ($flowMessages as $flowMessage) {
                    $flowMessage->setFinish($stubHash);
                    $this->storage?->appendFlowMessage($flowMessage);
                }

                $flowException = FlowException::create(
                    flowHash: $flow->getHash(),
                    flowRuntimeHash: $flow->getRuntimeHash(),
                    flowType: $flow->getType(),
                    stubSource: $stubSource,
                    stubHash: $stubHash,
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
                $flowMessage->setFinish($stubHash);
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

            if (is_bool($processResult)) {
                $flowResult = FlowResult::create(
                    flowHash: $flow->getHash(),
                    flowRuntimeHash: $flow->getRuntimeHash(),
                    stubSource: $stubSource,
                    stubHash: $stubHash,
                    result: $processResult,
                );
                $flow->addResult($flowResult);
                $this->storage?->appendFlowResult($flowResult);
            }

            if ($processResult instanceof MessageReturnInterface) {
                $returnFlowMessage = FlowMessage::create(
                    flowHash: $flow->getHash(),
                    flowRuntimeHash: $flow->getRuntimeHash(),
                    stubSource: $stubSource,
                    stubHash: $stubHash,
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
