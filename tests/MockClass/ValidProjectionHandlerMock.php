<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Attribute\FlowProjection;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowMessageReadonly;
use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;

#[FlowProjection(['flow.test.v1', 'flow.test.v2'])]
class ValidProjectionHandlerMock implements ProjectionHandlerInterface
{
    /**
     * @var list<array{messageSource: string, flowMessage: FlowMessage}>
     */
    public array $calls = [];

    public function project(FlowMessageReadonly $flowMessageReadonly): void
    {
        $this->calls[] = [
            'messageSource' => $flowMessageReadonly->getMessageSource(),
            'flowMessageReadony' => $flowMessageReadonly,
        ];
    }
}
