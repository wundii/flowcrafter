<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;

#[FlowSchedule('* * * * *', name: 'test-schedule')]
class ScheduleMock extends AbstractSchedule
{
    public bool $executed = false;

    public function process(): void
    {
        $this->executed = true;
        $this->enqueue(WorkflowMock::class, new MessageInitMock('scheduled'));
    }
}
