<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\ScheduleFailMock;
use Tests\MockClass\ScheduleMock;
use Tests\MockClass\ScheduleNonDueMock;
use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\DependencyInjection\DependencyRegistry;
use Wundii\Flowcrafter\FlowScheduler;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;

final class FlowSchedulerTest extends TestCase
{
    public function testGetScheduleAttributesReturnsConfiguredSchedules(): void
    {
        $storage = $this->createStub(StorageInterface::class);
        $queue = $this->createStub(QueueInterface::class);

        $schedules = [
            ScheduleMock::class => new FlowSchedule('* * * * *', name: 'test-schedule'),
        ];

        $flowScheduler = new FlowScheduler($storage, $queue, new DependencyRegistry(), $schedules);

        $attributes = $flowScheduler->getScheduleAttributes();
        $this->assertCount(1, $attributes);
        $this->assertArrayHasKey(ScheduleMock::class, $attributes);
        $this->assertSame('* * * * *', $attributes[ScheduleMock::class]->expression);
        $this->assertSame('test-schedule', $attributes[ScheduleMock::class]->name);
    }

    public function testTickExecutesDueSchedule(): void
    {
        $storage = $this->createStub(StorageInterface::class);
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->once())
            ->method('appendObserveItem');

        $schedules = [
            ScheduleMock::class => new FlowSchedule('* * * * *', name: 'test-schedule'),
        ];

        $flowScheduler = new FlowScheduler($storage, $queue, new DependencyRegistry(), $schedules);

        $messages = [];
        $logger = static function (string $message) use (&$messages): void {
            $messages[] = $message;
        };

        $flowScheduler->tick($logger);

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('test-schedule', $messages[0]);
    }

    public function testTickSkipsNonDueSchedule(): void
    {
        $storage = $this->createStub(StorageInterface::class);
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->never())
            ->method('appendObserveItem');

        $schedules = [
            ScheduleNonDueMock::class => new FlowSchedule('0 0 1 1 *', name: 'non-due'),
        ];

        $flowScheduler = new FlowScheduler($storage, $queue, new DependencyRegistry(), $schedules);
        $flowScheduler->tick();
    }

    public function testTickDoesNotExecuteSameMinuteTwice(): void
    {
        $storage = $this->createStub(StorageInterface::class);
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->once())
            ->method('appendObserveItem');

        $schedules = [
            ScheduleMock::class => new FlowSchedule('* * * * *', name: 'test-schedule'),
        ];

        $flowScheduler = new FlowScheduler($storage, $queue, new DependencyRegistry(), $schedules);
        $flowScheduler->tick();
        $flowScheduler->tick();
    }

    public function testTickLogsErrorsAndContinues(): void
    {
        $storage = $this->createStub(StorageInterface::class);
        $queue = $this->createStub(QueueInterface::class);

        $schedules = [
            ScheduleFailMock::class => new FlowSchedule('* * * * *', name: 'fail-schedule'),
        ];

        $flowScheduler = new FlowScheduler($storage, $queue, new DependencyRegistry(), $schedules);

        $messages = [];
        $logger = static function (string $message) use (&$messages): void {
            $messages[] = $message;
        };

        $flowScheduler->tick($logger);

        $this->assertCount(2, $messages);
        $this->assertStringContainsString('Schedule: fail-schedule', $messages[0]);
        $this->assertStringContainsString('Schedule error', $messages[1]);
        $this->assertStringContainsString('fail-schedule', $messages[1]);
        $this->assertStringContainsString('Schedule process failed', $messages[1]);
    }

    public function testTickWithEmptySchedulesDoesNothing(): void
    {
        $storage = $this->createStub(StorageInterface::class);
        $queue = $this->createStub(QueueInterface::class);

        $flowScheduler = new FlowScheduler($storage, $queue, new DependencyRegistry(), []);
        $flowScheduler->tick();

        $this->assertCount(0, $flowScheduler->getScheduleAttributes());
    }
}
