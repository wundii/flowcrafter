<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

class WorkflowEnqueueTriggerMock implements FlowInterface
{
    public static function schema(): FlowSchema
    {
        $flowBuilder = new FlowBuilder(
            'flow.workflow.enqueuetrigger.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(EnqueueTriggerStepMock::class);

        return $flowBuilder->build();
    }
}
