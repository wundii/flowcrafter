<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Attribute\FlowProjection;
use Wundii\Flowcrafter\Attribute\FlowProjectionMessage;
use Wundii\Flowcrafter\FlowMessageReadonly;
use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;

#[FlowProjection(['flow.workflow.v1'])]
class WorkflowProjectionMock implements ProjectionHandlerInterface
{
    /**
     * @var list<array{method: string, messageSource: string, flowMessage: FlowMessageReadonly}>
     */
    public array $calls = [];

    #[FlowProjectionMessage(MessageDataSecondMock::class)]
    public function onMessageDataSecond(FlowMessageReadonly $flowMessageReadonly): void
    {
        // throw new \Exception($flowMessageReadonly->getFlowType());
    }

    #[FlowProjectionMessage(MessageDataMock::class)]
    public function onMessageData(FlowMessageReadonly $flowMessageReadonly): void
    {
        $this->calls[] = [
            'method' => 'onInit',
            'messageSource' => $flowMessageReadonly->getMessageSource(),
            'flowMessage' => $flowMessageReadonly,
        ];
    }

    #[FlowProjectionMessage(MessageReturnMock::class)]
    public function onMessageReturn(FlowMessageReadonly $flowMessageReadonly): void
    {
        // dump($flowMessageReadonly->getMessage());
    }
}
