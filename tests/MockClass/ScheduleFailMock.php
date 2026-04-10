<?php

declare(strict_types=1);

namespace Tests\MockClass;

use RuntimeException;
use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;

#[FlowSchedule('*/15 * * * *', name: 'fail-schedule', active: false)]
class ScheduleFailMock extends AbstractSchedule
{
    public function process(): void
    {
        throw new RuntimeException('Schedule process failed');
    }
}
