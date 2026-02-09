<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

class FlowRunner
{
    private Flow $flow;

    private bool|MessageReturnInterface $messageReturn = false;

    /**
     * @param class-string<FlowInterface> $flowSource
     */
    public function __construct(
        string $type,
        string $flowSource,
    ) {
        Assert::classString(
            $flowSource,
            FlowInterface::class,
            'The flow must be a class implementing FlowInterface'
        );

        $this->flow = Flow::create(
            $type,
            $flowSource,
        );
    }

    /**
     * @return FlowMessage[]
     */
    public function getFlowMessages(): array
    {
        return $this->flow->getFlowMessages();
    }

    public function run(
        MessageInterface $message,
    ): bool|MessageReturnInterface {
        $flowSchema = $this->flow->getSchema();
        $messageToStubsMap = $flowSchema->getMessageToSubsMap();

        $executed = [];
        $this->executeStubsRecursive($message, $messageToStubsMap, $executed);

        return $this->messageReturn ?? false;
    }

    /**
     * @param array<string, Stub[]> $map
     * @param string[] $executed
     */
    private function executeStubsRecursive(MessageInterface $message, array $map, array &$executed, ?string $flowMessageHash = null): void
    {
        $messageClass = get_class($message);

        if (!isset($map[$messageClass])) {
            return;
        }

        foreach ($map[$messageClass] as $stub) {
            $stubSource = $stub->getSource();

            $stubKey = $stubSource . ':' . $messageClass;
            if (in_array($stubKey, $executed, true)) {
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

            $executed[] = $stubKey;
            $stubInstance = new $stubSource($stub->getMessageEnum()->value, reset($messages));
            $processResult = $stubInstance->process();

            array_map(
                static fn (FlowMessage $flowMessage) => $flowMessage->setFinish(),
                $flowMessages,
            );

            if (is_object($processResult) && !$processResult instanceof MessageReturnInterface) {
                $this->executeStubsRecursive($processResult, $map, $executed, $flowMessage->getHash());
                continue;
            }

            if ($this->messageReturn instanceof MessageReturnInterface) {
                continue;
            }

            $this->messageReturn = $processResult;
        }
    }
}
