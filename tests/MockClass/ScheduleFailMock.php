<?php

declare(strict_types=1);

namespace Tests\MockClass;

use RuntimeException;
use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;

#[FlowSchedule('* * * * *', name: 'fail-schedule')]
class ScheduleFailMock extends AbstractSchedule
{
    public function process(): void
    {
        throw new RuntimeException('Schedule process failed');
    }
}
