<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

class WorkflowRunTriggerMock implements FlowInterface
{
    public static function schema(): FlowSchema
    {
        $flowBuilder = new FlowBuilder(
            'flow.workflow.runtrigger.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(RunTriggerStepMock::class);

        return $flowBuilder->build();
    }
}
