<?php

declare(strict_types=1);

namespace Tests\Schedule;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Schedule\ScheduleException;

final class ScheduleExceptionTest extends TestCase
{
    private ScheduleException $scheduleException;

    private DateTimeImmutable $time;

    protected function setUp(): void
    {
        $this->time = new DateTimeImmutable('2025-01-15 10:00:00');
        $this->scheduleException = ScheduleException::create(
            scheduleClass: 'MySchedule',
            scheduleName: 'daily-job',
            scheduleExpression: '0 8 * * *',
            code: '42',
            message: 'Something went wrong',
            file: '/app/src/MySchedule.php',
            line: 99,
            traceString: '#0 ...',
            time: $this->time,
            hash: 'test-hash-1234',
        );
    }

    public function testGetters(): void
    {
        $this->assertSame('MySchedule', $this->scheduleException->getScheduleClass());
        $this->assertSame('daily-job', $this->scheduleException->getScheduleName());
        $this->assertSame('0 8 * * *', $this->scheduleException->getScheduleExpression());
        $this->assertSame('42', $this->scheduleException->getCode());
        $this->assertSame('Something went wrong', $this->scheduleException->getMessage());
        $this->assertSame('/app/src/MySchedule.php', $this->scheduleException->getFile());
        $this->assertSame(99, $this->scheduleException->getLine());
        $this->assertSame('#0 ...', $this->scheduleException->getTraceString());
        $this->assertSame($this->time, $this->scheduleException->getTime());
        $this->assertSame('test-hash-1234', $this->scheduleException->getHash());
    }

    public function testCreateGeneratesHashAndTimeWhenOmitted(): void
    {
        $scheduleException = ScheduleException::create(
            scheduleClass: 'ASchedule',
            scheduleName: 'job',
            scheduleExpression: '* * * * *',
            code: '0',
            message: 'error',
            file: 'file.php',
            line: 1,
            traceString: '',
        );

        $this->assertNotEmpty($scheduleException->getHash());
        $this->assertInstanceOf(DateTimeInterface::class, $scheduleException->getTime());
    }

    public function testJsonSerialize(): void
    {
        $data = $this->scheduleException->jsonSerialize();

        $this->assertSame('MySchedule', $data['scheduleClass']);
        $this->assertSame('daily-job', $data['scheduleName']);
        $this->assertSame('0 8 * * *', $data['scheduleExpression']);
        $this->assertSame('42', $data['code']);
        $this->assertSame('Something went wrong', $data['message']);
        $this->assertSame('/app/src/MySchedule.php', $data['file']);
        $this->assertSame(99, $data['line']);
        $this->assertSame('#0 ...', $data['traceString']);
        $this->assertSame($this->time->format(DateTimeInterface::RFC3339_EXTENDED), $data['time']);
        $this->assertSame('test-hash-1234', $data['hash']);
    }
}
