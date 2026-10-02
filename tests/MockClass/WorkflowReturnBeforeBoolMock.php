<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

class WorkflowReturnBeforeBoolMock implements FlowInterface
{
    public static function schema(): FlowSchema
    {
        $flowBuilder = new FlowBuilder(
            'flow.workflow.return-before-bool.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(StepMock::class);
        $flowBuilder->addStep(ReturnEarlyStepMock::class);
        $flowBuilder->addStep(FalseStepMock::class);

        return $flowBuilder->build();
    }
}
