<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Closure;
use Cron\CronExpression;
use DateTimeImmutable;
use RuntimeException;
use Throwable;
use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Console\Heartbeat;
use Wundii\Flowcrafter\Interface\ScheduleInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;
use Wundii\Flowcrafter\Schedule\ScheduleDiscovery;
use Wundii\Flowcrafter\Schedule\ScheduleException;

final class FlowScheduler
{
    /**
     * @var array<class-string<ScheduleInterface>, FlowSchedule>
     */
    private readonly array $scheduleAttributes;

    /**
     * @var array<class-string<ScheduleInterface>, string>
     */
    private array $lastExecutedMinute = [];

    /**
     * @param array<int|class-string, class-string|object> $dependenciesInjection
     * @param array<class-string<ScheduleInterface>, FlowSchedule>|null $scheduleAttributes
     */
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly array $dependenciesInjection,
        ?array $scheduleAttributes = null,
    ) {
        $this->scheduleAttributes = $scheduleAttributes ?? ScheduleDiscovery::discover();
    }

    /**
     * @return array<class-string<ScheduleInterface>, FlowSchedule>
     */
    public function getScheduleAttributes(): array
    {
        return $this->scheduleAttributes;
    }

    /**
     * @param (Closure(string): void)|null $logger
     */
    public function tick(?Closure $logger = null): void
    {
        $now = new DateTimeImmutable();
        $currentMinute = $now->format('Y-m-d H:i');

        foreach ($this->scheduleAttributes as $scheduleClass => $attribute) {
            if (($this->lastExecutedMinute[$scheduleClass] ?? '') === $currentMinute) {
                continue;
            }

            $cronExpression = new CronExpression($attribute->expression);

            if (!$cronExpression->isDue($now)) {
                continue;
            }

            if ($attribute->active === false) {
                continue;
            }

            $name = $attribute->name ?? $scheduleClass;

            try {
                $schedule = FlowContainerFactory::build(
                    autowireClasses: [$scheduleClass],
                    dependencies: $this->dependenciesInjection,
                )->get($scheduleClass);

                if (!$schedule instanceof AbstractSchedule) {
                    throw new RuntimeException(sprintf(
                        'Schedule %s must extend AbstractSchedule.',
                        $scheduleClass,
                    ));
                }

                $schedule->setContext($this->storage, $this->dependenciesInjection);

                if ($logger instanceof Closure) {
                    $logger(sprintf(
                        '%s - Schedule: %s',
                        $now->format('Y-m-d H:i:s'),
                        $name,
                    ));
                }

                $schedule->process();
                $this->lastExecutedMinute[$scheduleClass] = $currentMinute;
            } catch (Throwable $e) {
                $this->lastExecutedMinute[$scheduleClass] = $currentMinute;

                if ($logger instanceof Closure) {
                    $logger(sprintf(
                        '%s - Schedule error [%s]: %s',
                        $now->format('Y-m-d H:i:s'),
                        $name,
                        $e->getMessage(),
                    ));
                }

                $this->storage->appendScheduleException(
                    ScheduleException::create(
                        scheduleClass: $scheduleClass,
                        scheduleName: $name,
                        scheduleExpression: $attribute->expression,
                        code: $e->getCode(),
                        message: $e->getMessage(),
                        file: $e->getFile(),
                        line: $e->getLine(),
                        traceString: $e->getTraceAsString(),
                        time: $now,
                    )
                );
            }
        }
    }

    /**
     * @param (Closure(string): void)|null $logger
     */
    public function run(
        ?Closure $logger = null,
        ?Heartbeat $heartbeat = null,
    ): void {
        $this->tick($logger);

        $heartbeat?->touchIfDue();

        $secondsUntilNextMinute = 60 - (int) date('s');
        if ($secondsUntilNextMinute > 0) {
            sleep($secondsUntilNextMinute);
        }
    }
}
