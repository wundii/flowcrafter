<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Exception;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Throwable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;
use Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity;

class FlowRunner
{
    private ?Flow $flow = null;

    private ?ContainerBuilder $container = null;

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
     * @var FlowOutput[]
     */
    private array $outputLog = [];

    /**
     * @param class-string<FlowInterface> $flowSource
     * @param array<int|class-string, class-string|object> $dependenciesInjection
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
     * @return FlowOutput[]
     */
    public function getOutputLog(): array
    {
        return $this->outputLog;
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
        $flowInstance = $flowHash !== null ? $this->storage?->findFlowInstanceByHash($flowHash) : null;

        $flowSchemaHash = $flowInstance instanceof FlowInstanceEntity ? $flowInstance->flowSchemaHash : null;
        $flowSubject = $flowInstance instanceof FlowInstanceEntity ? $flowInstance->flowSubject : $this->flowSubject;

        $this->flow = Flow::create(
            flowType: $this->type,
            flowSource: $this->flowSource,
            flowSchemaHash: $flowSchemaHash,
            flowSubject: $flowSubject,
            flowHash: $flowHash,
        );

        $flow = $this->flow;
        $flow->setIncludeStubs($includeStubs);

        if (!$flow->isExecutable()) {
            throw new RuntimeException('Flow is not executable, because the flowSchemaHash is different from the stored version');
        }

        $flow->addRun($queueId);

        $flowSchema = $flow->getSchema();

        $this->storage?->registerFlowSchema($flowSchema);
        $this->storage?->registerFlowInstance($flow);
        $this->storage?->appendFlowRun($flow, $queueId); #start to run the flow
        $this->container = $this->buildContainer($flowSchema);
        $this->executedStubKey = [];
        $this->includeStubs = $includeStubs;
        $this->messageToStubsMap = $flowSchema->getMessageToSubsMap();

        $this->executeStubsRecursive($flow, $message);

        $this->storage?->appendFlow($flow);

        return $this->messageReturn ?: false;
    }

    /**
     * @param class-string<StubInterface> $stubSource
     * @param FlowMessage[] $flowMessages
     * @throws Exception
     */
    public function createInstance(string $stubSource, array $flowMessages): StubInterface
    {
        if (!$this->container instanceof ContainerBuilder) {
            throw new RuntimeException('Container is not initialized. Call run() first.');
        }

        foreach ($flowMessages as $flowMessage) {
            $message = $flowMessage->getMessage();
            $this->container->set(get_class($message), $message);
        }

        $stubInstance = $this->container->get($stubSource);
        if (!$stubInstance instanceof StubInterface) {
            throw new RuntimeException('Stub instance must implement StubInterface.');
        }

        return $stubInstance;
    }

    private function buildContainer(FlowSchema $flowSchema): ContainerBuilder
    {
        $autowireClasses = [];
        $syntheticClasses = [];

        foreach ($flowSchema->stubs() as $stub) {
            $autowireClasses[] = $stub->getSource();

            foreach ($stub->getMessages() as $messageClass) {
                $syntheticClasses[$messageClass] = $messageClass;
            }

            foreach ($stub->getReturnTypes() as $returnType) {
                $syntheticClasses[$returnType] = $returnType;
            }
        }

        return FlowContainerFactory::build(
            autowireClasses: $autowireClasses,
            syntheticServices: array_values($syntheticClasses),
            dependencies: $this->dependenciesInjection,
        );
    }

    /**
     * @throws Throwable
     */
    private function executeStubsRecursive(Flow $flow, MessageInterface $message, ?string $flowMessageHash = null): void
    {
        $messageClass = get_class($message);

        if (!array_key_exists($messageClass, $this->messageToStubsMap)) {
            return;
        }

        foreach ($this->messageToStubsMap[$messageClass] as $stub) {
            $stubSource = Source::stub($stub->getSource());

            if ($this->includeStubs !== [] && !in_array($stubSource->stubSource, $this->includeStubs, true)) {
                continue;
            }

            $stubKey = $stubSource->stubSource . ':' . $messageClass;
            if (in_array($stubKey, $this->executedStubKey, true)) {
                continue;
            }

            $messageSource = Source::message($messageClass);
            $this->storage?->registerMessageSource($messageSource);

            $flowMessageWait = FlowMessage::create(
                flowHash: $flow->getHash(),
                flowRuntimeHash: $flow->getRuntimeHash(),
                stubSource: $stubSource->stubSource,
                stubHash: $stubSource->stubHash,
                messageTypeEnum: MessageTypeEnum::WAIT,
                messageHash: $messageSource->messageHash,
                message: $message,
                predecessorHash: $flowMessageHash,
            );

            $flow->addMessage($flowMessageWait);

            $flowMessages = $flow->executableMessages($stubSource->stubSource);
            if ($flowMessages === []) {
                continue;
            }

            $this->executedStubKey[] = $stubKey;

            $this->storage?->registerStubSource($stubSource);

            try {
                $stubInstance = $this->createInstance($stubSource->stubSource, $flowMessages);
                ob_start();
                $processResult = $stubInstance->process();
                $stubOutput = ob_get_clean();
                if ($stubOutput !== '' && $stubOutput !== false) {
                    $this->outputLog[] = FlowOutput::create($stubSource->stubSource, $stubOutput);
                }
            } catch (Throwable $exception) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }

                foreach ($flowMessages as $flowMessage) {
                    $flowMessage->setFinish();
                    $this->storage?->appendFlowMessage($flowMessage);
                }

                $flowException = FlowException::create(
                    flowHash: $flow->getHash(),
                    flowRuntimeHash: $flow->getRuntimeHash(),
                    flowType: $flow->getType(),
                    stubSource: $stubSource->stubSource,
                    stubHash: $stubSource->stubHash,
                    code: $exception->getCode(),
                    message: $exception->getMessage(),
                    file: $exception->getFile(),
                    line: $exception->getLine(),
                    traceString: $exception->getTraceAsString(),
                );

                $flow->addException($flowException);
                $this->storage?->appendFlowException($flowException);
                $this->storage?->appendFlow($flow);

                throw $exception;
            }

            foreach ($flowMessages as $flowMessage) {
                $flowMessage->setFinish();
                $this->storage?->appendFlowMessage($flowMessage);
            }

            if ($processResult instanceof MessageInterface && !$processResult instanceof MessageReturnInterface) {
                $this->executeStubsRecursive($flow, $processResult, $flowMessageWait->getHash());
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
                    stubSource: $stubSource->stubSource,
                    stubHash: $stubSource->stubHash,
                    result: $processResult,
                );
                $flow->addResult($flowResult);
                $this->storage?->appendFlowResult($flowResult);
            }

            if ($processResult instanceof MessageReturnInterface) {
                $messageSource = Source::message(get_class($processResult));
                $this->storage?->registerMessageSource($messageSource);
                $returnFlowMessage = FlowMessage::create(
                    flowHash: $flow->getHash(),
                    flowRuntimeHash: $flow->getRuntimeHash(),
                    stubSource: $stubSource->stubSource,
                    stubHash: $stubSource->stubHash,
                    messageTypeEnum: MessageTypeEnum::FINISH,
                    messageHash: $messageSource->messageHash,
                    message: $processResult,
                    predecessorHash: $flowMessageWait->getHash(),
                );
                $flow->addMessage($returnFlowMessage);
                $this->storage?->appendFlowMessage($returnFlowMessage);
            }
        }
    }
}
