<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;

class FlowRunner
{
    private Flow $flow;

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
        string $type,
        string $flowSource,
        ?string $flowSubject = null,
        ?string $flowHash = null,
        private readonly ?StorageInterface $storage = null,
    ) {
        Assert::classString(
            $flowSource,
            FlowInterface::class,
            'The flow must be a class implementing FlowInterface'
        );

        $this->flow = Flow::create(
            $type,
            $flowSource,
            $flowSubject,
            $flowHash,
        );
    }

    public function getFlow(): Flow
    {
        return $this->flow;
    }

    public function run(
        MessageInterface $message,
    ): bool|MessageReturnInterface {
        $flowSchema = $this->flow->getSchema();

        $this->storage?->initialize();
        $this->storage?->registeredFlowSchema($flowSchema);
        $this->storage?->writeFlow($this->flow);
        $this->executedStubKey = [];
        $this->messageToStubsMap = $flowSchema->getMessageToSubsMap();

        $this->executeStubsRecursive($message);

        return $this->messageReturn ?? false;
    }

    private function executeStubsRecursive(MessageInterface $message, ?string $flowMessageHash = null): void
    {
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
                flowHash: $this->flow->getHash(),
                flowRuntimeHash: $this->flow->getRuntimeHash(),
                stubSource: $stubSource,
                messageTypeEnum: MessageTypeEnum::WAIT,
                predecessorHash: $flowMessageHash,
                message: $message,
            );

            $this->flow->addMessage($flowMessage);

            $flowMessages = $this->flow->executableMessages($stubSource);
            if ($flowMessages === []) {
                continue;
            }

            $messages = array_map(
                static fn (FlowMessage $flowMessage): MessageInterface => $flowMessage->getMessage(),
                $flowMessages,
            );

            $this->executedStubKey[] = $stubKey;
            $stubInstance = new $stubSource($stub->getMessageEnum()->value, reset($messages));
            $processResult = $stubInstance->process();

            foreach ($flowMessages as $flowMessage) {
                $flowMessage->setFinish();
                $this->storage?->writeFlowMessage($flowMessage);
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
