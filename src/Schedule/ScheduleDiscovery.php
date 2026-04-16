<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Schedule;

use ReflectionClass;
use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\ClassResolver;
use Wundii\Flowcrafter\Interface\ScheduleInterface;

final class ScheduleDiscovery
{
    /**
     * @var array<class-string<ScheduleInterface>, FlowSchedule>|null
     */
    private static ?array $cache = null;

    /**
     * @return array<class-string<ScheduleInterface>, FlowSchedule>
     */
    public static function discover(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $schedules = [];

        foreach (ClassResolver::resolve() as $className) {
            if (!is_a($className, ScheduleInterface::class, true)) {
                continue;
            }

            $reflectionClass = new ReflectionClass($className);
            $attributes = $reflectionClass->getAttributes(FlowSchedule::class);

            if ($attributes === []) {
                continue;
            }

            $schedules[$className] = $attributes[0]->newInstance();
        }

        self::$cache = $schedules;

        return self::$cache;
    }
}
