<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;

#[FlowSchedule('0 0 1 1 *', name: 'non-due-schedule', group: 'flowGroup')]
class ScheduleNonDueMock extends AbstractSchedule
{
    public bool $executed = false;

    public function process(): void
    {
        $this->executed = true;
    }
}
