<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Throwable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;

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
        $this->flow = Flow::create(
            $this->type,
            $this->flowSource,
            $this->flowSubject,
            $flowHash,
        );

        $flowSchema = $this->flow->getSchema();

        $this->storage?->initializeDatabase();
        $this->storage?->registerFlowSchema($flowSchema);
        $this->storage?->registerFlowInstance($this->flow);
        $this->storage?->appendFlowRun($this->flow, $queueId); #start to run the flow
        $this->executedStubKey = [];
        $this->messageToStubsMap = $flowSchema->getMessageToSubsMap();

        $this->executeStubsRecursive($message);

        return $this->messageReturn ?? false;
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

            $messages = array_map(
                static fn (FlowMessage $flowMessage): MessageInterface => $flowMessage->getMessage(),
                $flowMessages,
            );

            $this->executedStubKey[] = $stubKey;

            try {
                $stubInstance = new $stubSource($stub->getMessageEnum()->value, reset($messages));
                $processResult = $stubInstance->process();
            } catch (Throwable $exception) {
                $flowException = FlowException::create(
                    flowHash: $flow->getHash(),
                    flowRuntimeHash: $flow->getRuntimeHash(),
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
        }
    }
}
