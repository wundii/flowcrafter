<?php

declare(strict_types=1);

namespace Tests\Schedule;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\ScheduleMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Interface\StorageInterface;

final class AbstractScheduleTest extends TestCase
{
    public function testEnqueueCallsAppendObserveItem(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('appendObserveItem')
            ->with(
                'flow.workflow.v1',
                WorkflowMock::class,
                null,
                MessageInitMock::class,
                [
                    'data' => 'scheduled',
                ],
                [],
                null,
            );

        $scheduleMock = new ScheduleMock();
        $scheduleMock->setContext($storage, []);
        $scheduleMock->process();
    }

    public function testProcessWithoutContextThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Schedule context not initialized');

        $scheduleMock = new ScheduleMock();
        $scheduleMock->process();
    }
}
