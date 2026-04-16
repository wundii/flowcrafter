<?php

declare(strict_types=1);

namespace Tests\Storage\Entity;

use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Storage\Entity\ExceptionStatsEntity;

final class ExceptionStatsEntityTest extends TestCase
{
    public function testConstructorAndPublicProperties(): void
    {
        $exceptionStatsEntity = new ExceptionStatsEntity(
            date: '2025-01-15',
            flow: 3,
            schedule: 1,
        );

        $this->assertSame('2025-01-15', $exceptionStatsEntity->date);
        $this->assertSame(3, $exceptionStatsEntity->flow);
        $this->assertSame(1, $exceptionStatsEntity->schedule);
    }

    public function testJsonSerialize(): void
    {
        $exceptionStatsEntity = new ExceptionStatsEntity(
            date: '2025-06-01',
            flow: 5,
            schedule: 2,
        );

        $this->assertSame([
            'date' => '2025-06-01',
            'flow' => 5,
            'schedule' => 2,
        ], $exceptionStatsEntity->jsonSerialize());
    }
}
