<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Attribute\FlowEphemeral;
use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

#[FlowEphemeral(expiryDays: 7)]
class WorkflowEphemeralMock implements FlowInterface
{
    public static function schema(): FlowSchema
    {
        $flowBuilder = new FlowBuilder(
            'flow.workflow.ephemeral.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(StepMock::class);
        $flowBuilder->addStep(NextStepMock::class);
        $flowBuilder->addStep(OtherStepMock::class);
        $flowBuilder->addStep(PostStepMock::class);

        return $flowBuilder->build();
    }
}
