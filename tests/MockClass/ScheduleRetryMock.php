<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;

#[FlowSchedule('*/10 * * * *', name: 'test-schedule-retry', group: 'flowGroup', active: false)]
class ScheduleRetryMock extends AbstractSchedule
{
    public function process(): void
    {
        RetryStepMock::configure(2);

        $this->run(
            flowSource: WorkflowRetryMock::class,
            message: new MessageInitMock('retry-scheduled'),
            flowSubject: 'retry-subject-' . random_int(1, 99999),
        );
    }
}
