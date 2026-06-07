<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

class WorkflowArrayMock implements FlowInterface
{
    public static function schema(): FlowSchema
    {
        $flowBuilder = new FlowBuilder(
            'flow.workflow.array.v1',
            MessageWithArrayMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(ArrayStepMock::class);

        return $flowBuilder->build();
    }
}
