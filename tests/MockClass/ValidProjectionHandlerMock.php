<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Attribute\FlowProjection;
use Wundii\Flowcrafter\Attribute\FlowProjectionMessage;
use Wundii\Flowcrafter\FlowMessageReadonly;
use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;

#[FlowProjection(['flow.test.v1', 'flow.test.v2'])]
class ValidProjectionHandlerMock implements ProjectionHandlerInterface
{
    /**
     * @var list<array{method: string, messageSource: string, flowMessage: FlowMessageReadonly}>
     */
    public array $calls = [];

    #[FlowProjectionMessage(MessageInitMock::class)]
    public function onInit(FlowMessageReadonly $flowMessageReadonly): void
    {
        $this->calls[] = [
            'method' => 'onInit',
            'messageSource' => $flowMessageReadonly->getMessageSource(),
            'flowMessage' => $flowMessageReadonly,
        ];
    }

    #[FlowProjectionMessage(MessageReturnMock::class)]
    public function onReturn(FlowMessageReadonly $flowMessageReadonly): void
    {
        $this->calls[] = [
            'method' => 'onReturn',
            'messageSource' => $flowMessageReadonly->getMessageSource(),
            'flowMessage' => $flowMessageReadonly,
        ];
    }
}
