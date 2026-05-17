<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Throwable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StepInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity;

class FlowRunner
{
    private ?Flow $flow = null;

    private ?ContainerBuilder $container = null;

    /**
     * @var string[]
     */
    private array $executedStepKey;

    /**
     * @var array<string, Step[]>
     */
    private array $messageToStepsMap;

    /**
     * @var class-string[]
     */
    private array $includeSteps = [];

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
     * @param class-string[] $includeSteps
     */
    public function run(
        MessageInterface $message,
        ?string $flowHash = null,
        ?string $queueId = null,
        array $includeSteps = [],
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
        $flow->setIncludeSteps($includeSteps);

        if (!$flow->isExecutable()) {
            throw new InvalidArgumentException('Flow is not executable, because the flowSchemaHash is different from the stored version');
        }

        $flow->addRun($queueId);

        $flowSchema = $flow->getSchema();

        $this->storage?->registerFlowSchema($flowSchema);
        $this->storage?->registerFlowInstance($flow);
        $this->storage?->appendFlowRun($flow, $queueId); #start to run the flow
        $this->container = $this->buildContainer($flowSchema);
        $this->executedStepKey = [];
        $this->messageToStepsMap = $flowSchema->getMessageToStepsMap();
        $this->includeSteps = $this->expandIncludeSteps($includeSteps, $flowSchema);

        $this->injectHistoricalMessages($flow, $message, $flowHash);

        $this->executeStepsRecursive($flow, $message);

        $this->storage?->appendFlow($flow);

        return $this->messageReturn ?: false;
    }

    /**
     * @param class-string<StepInterface> $stepSource
     * @param FlowMessage[] $flowMessages
     * @throws Exception
     */
    public function createInstance(string $stepSource, array $flowMessages): StepInterface
    {
        if (!$this->container instanceof ContainerBuilder) {
            throw new RuntimeException('Container is not initialized. Call run() first.');
        }

        foreach ($flowMessages as $flowMessage) {
            $message = $flowMessage->getMessage();
            $this->container->set(get_class($message), $message);
        }

        $stepInstance = $this->container->get($stepSource);
        if (!$stepInstance instanceof StepInterface) {
            throw new RuntimeException('Step instance must implement StepInterface.');
        }

        return $stepInstance;
    }

    private function injectHistoricalMessages(Flow $flow, MessageInterface $entryMessage, ?string $flowHash): void
    {
        if (!$this->storage instanceof StorageInterface || $flowHash === null) {
            return;
        }

        $flowSchema = $flow->getSchema();
        $entryMessageClass = get_class($entryMessage);

        /** @var array<class-string<MessageInterface>, bool> $reachable */
        $reachable = [
            $entryMessageClass => true,
        ];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($flowSchema->steps() as $step) {
                if ($this->includeSteps !== [] && !in_array($step->getSource(), $this->includeSteps, true)) {
                    continue;
                }

                $allReachable = true;
                foreach ($step->getMessages() as $msgClass) {
                    if (!isset($reachable[$msgClass])) {
                        $allReachable = false;
                        break;
                    }
                }

                if (!$allReachable) {
                    continue;
                }

                foreach ($step->getReturnTypes() as $returnType) {
                    if (!isset($reachable[$returnType])) {
                        $reachable[$returnType] = true;
                        $changed = true;
                    }
                }
            }
        }

        /** @var array<class-string<StepInterface>, array<class-string<MessageInterface>, bool>> $missingPairs */
        $missingPairs = [];
        foreach ($flowSchema->steps() as $step) {
            if ($this->includeSteps !== [] && !in_array($step->getSource(), $this->includeSteps, true)) {
                continue;
            }

            foreach ($step->getMessages() as $messageClass) {
                if ($messageClass === $entryMessageClass) {
                    continue;
                }

                if (isset($reachable[$messageClass])) {
                    continue;
                }

                $missingPairs[$step->getSource()][$messageClass] = true;
            }
        }

        if ($missingPairs === []) {
            return;
        }

        $historicalFlow = $this->storage->findFlowByHash($flowHash);
        if (!$historicalFlow instanceof Flow) {
            return;
        }

        /** @var array<class-string<StepInterface>, array<class-string<MessageInterface>, FlowMessage>> $latestByPair */
        $latestByPair = [];
        foreach ($historicalFlow->getFlowMessages() as $flowMessage) {
            $hStep = $flowMessage->getStepSource();
            $hMsg = $flowMessage->getMessageSource();
            if (!isset($missingPairs[$hStep][$hMsg])) {
                continue;
            }

            if ($flowMessage->getMessage() instanceof FlowMessageReadOnly) {
                continue;
            }

            $latestByPair[$hStep][$hMsg] = $flowMessage;
        }

        foreach ($missingPairs as $stepSource => $messageClasses) {
            foreach (array_keys($messageClasses) as $messageClass) {
                $found = $latestByPair[$stepSource][$messageClass] ?? null;
                if ($found === null) {
                    continue;
                }

                $stepSourceObj = Source::step($stepSource);
                $messageSourceObj = Source::message($messageClass);

                $flow->addMessage(FlowMessage::create(
                    flowHash: $flow->getHash(),
                    flowRuntimeHash: $flow->getRuntimeHash(),
                    stepSource: $stepSource,
                    stepHash: $stepSourceObj->stepHash,
                    messageTypeEnum: MessageTypeEnum::WAIT,
                    messageHash: $messageSourceObj->messageHash,
                    message: $found->getMessage(),
                    predecessorHash: null,
                ));
            }
        }
    }

    /**
     * @param class-string[] $includeSteps
     * @return class-string[]
     */
    private function expandIncludeSteps(array $includeSteps, FlowSchema $flowSchema): array
    {
        if ($includeSteps === []) {
            return [];
        }

        $stepReturnMap = [];
        foreach ($flowSchema->steps() as $step) {
            $stepReturnMap[$step->getSource()] = $step->getReturnTypes();
        }

        $expanded = $includeSteps;
        $queue = $includeSteps;

        while ($queue !== []) {
            $stepSource = array_shift($queue);
            foreach ($stepReturnMap[$stepSource] ?? [] as $returnType) {
                foreach ($this->messageToStepsMap[$returnType] ?? [] as $downstreamStep) {
                    $downstreamSource = $downstreamStep->getSource();
                    if (!in_array($downstreamSource, $expanded, true)) {
                        $expanded[] = $downstreamSource;
                        $queue[] = $downstreamSource;
                    }
                }
            }
        }

        return $expanded;
    }

    private function buildContainer(FlowSchema $flowSchema): ContainerBuilder
    {
        $autowireClasses = [];
        $syntheticClasses = [];

        foreach ($flowSchema->steps() as $step) {
            $autowireClasses[] = $step->getSource();

            foreach ($step->getMessages() as $messageClass) {
                $syntheticClasses[$messageClass] = $messageClass;
            }

            foreach ($step->getReturnTypes() as $returnType) {
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
    private function executeStepsRecursive(Flow $flow, MessageInterface $message, ?string $flowMessageHash = null): void
    {
        $messageClass = get_class($message);

        if (!array_key_exists($messageClass, $this->messageToStepsMap)) {
            return;
        }

        foreach ($this->messageToStepsMap[$messageClass] as $step) {
            $stepSource = Source::step($step->getSource());

            if ($this->includeSteps !== [] && !in_array($stepSource->stepSource, $this->includeSteps, true)) {
                continue;
            }

            $stepKey = $stepSource->stepSource . ':' . $messageClass;
            if (in_array($stepKey, $this->executedStepKey, true)) {
                continue;
            }

            $messageSource = Source::message($messageClass);
            $this->storage?->registerMessageSource($messageSource);

            $flowMessageWait = FlowMessage::create(
                flowHash: $flow->getHash(),
                flowRuntimeHash: $flow->getRuntimeHash(),
                stepSource: $stepSource->stepSource,
                stepHash: $stepSource->stepHash,
                messageTypeEnum: MessageTypeEnum::WAIT,
                messageHash: $messageSource->messageHash,
                message: $message,
                predecessorHash: $flowMessageHash,
            );

            $flow->addMessage($flowMessageWait);

            $flowMessages = $flow->executableMessages($stepSource->stepSource);
            if ($flowMessages === []) {
                continue;
            }

            $this->executedStepKey[] = $stepKey;

            $this->storage?->registerStepSource($stepSource);

            $maxAttempts = $step->getRetries() + 1;
            $lastException = null;
            $processResult = false;

            for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
                try {
                    $stepInstance = $this->createInstance($stepSource->stepSource, $flowMessages);
                    ob_start();
                    $processResult = $stepInstance->process();
                    $stepOutput = ob_get_clean();
                    if ($stepOutput !== '' && $stepOutput !== false) {
                        $this->outputLog[] = FlowOutput::create($stepSource->stepSource, $stepOutput);
                    }

                    $lastException = null;
                    break;
                } catch (Throwable $exception) {
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }

                    $lastException = $exception;
                    if ($attempt < $maxAttempts) {
                        $flowRetry = FlowRetry::create(
                            flowHash: $flow->getHash(),
                            flowRuntimeHash: $flow->getRuntimeHash(),
                            stepSource: $stepSource->stepSource,
                            attempt: $attempt,
                            message: $exception->getMessage(),
                        );
                        $flow->addFlowRetry($flowRetry);
                        $this->storage?->appendFlowRetry($flowRetry);

                        usleep($step->getDelay() * 1000);
                    }
                }
            }

            if ($lastException instanceof Throwable) {
                foreach ($flowMessages as $flowMessage) {
                    $flowMessage->setFinish();
                    $this->storage?->appendFlowMessage($flowMessage);
                }

                $flowException = FlowException::create(
                    flowHash: $flow->getHash(),
                    flowRuntimeHash: $flow->getRuntimeHash(),
                    flowType: $flow->getType(),
                    stepSource: $stepSource->stepSource,
                    stepHash: $stepSource->stepHash,
                    code: $lastException->getCode(),
                    message: $lastException->getMessage(),
                    file: $lastException->getFile(),
                    line: $lastException->getLine(),
                    traceString: $lastException->getTraceAsString(),
                );

                $flow->addException($flowException);
                $this->storage?->appendFlowException($flowException);
                $this->storage?->appendFlow($flow);

                throw $lastException;
            }

            foreach ($flowMessages as $flowMessage) {
                $flowMessage->setFinish();
                $this->storage?->appendFlowMessage($flowMessage);
            }

            if ($processResult instanceof MessageInterface && !$processResult instanceof MessageReturnInterface) {
                $this->executeStepsRecursive($flow, $processResult, $flowMessageWait->getHash());
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
                    stepSource: $stepSource->stepSource,
                    stepHash: $stepSource->stepHash,
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
                    stepSource: $stepSource->stepSource,
                    stepHash: $stepSource->stepHash,
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
