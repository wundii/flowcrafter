<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

class EmptyWorkflowMock implements FlowInterface
{
    public static function schema(): FlowSchema
    {
        $flowBuilder = new FlowBuilder(
            'flow.empty.v1',
            EmptyInitMessage::class,
        );

        $flowBuilder->addStub(EmptyBoolStubMock::class);

        return $flowBuilder->build();
    }
}
