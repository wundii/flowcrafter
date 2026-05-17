<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;

#[FlowSchedule('*/5 * * * *', name: 'test-empty-schedule', active: false)]
class ScheduleEmptyMock extends AbstractSchedule
{
    public function process(): void
    {
        $this->enqueue(
            flowSource: WorkflowEmptyMock::class,
            message: new EmptyInitMessage(),
        );
    }
}
