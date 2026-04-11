<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

use DateTimeInterface;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\Schedule\ScheduleException;
use Wundii\Flowcrafter\Storage\Entity\ExceptionListEntity;
use Wundii\Flowcrafter\Storage\Entity\ExceptionStatsEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowListEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowStatsEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowTypeStatsEntity;

interface ServiceInterface
{
    public function isServiceStorageInitialized(): bool;

    public function countExceptions(?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): int;

    public function countScheduleExceptions(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int;

    public function countFlows(?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): int;

    public function countFlowsBySource(string $flowSource, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): int;

    public function countFlowsByType(string $flowType, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): int;

    public function countFlowsBySubject(string $flowSubject, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int;

    /**
     * @return ExceptionListEntity[]
     */
    public function findAllExceptions(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): iterable;

    /**
     * @return ExceptionStatsEntity[]
     */
    public function findExceptionStats(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable;

    /**
     * @return FlowListEntity[]
     */
    public function findAllFlows(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): iterable;

    /**
     * @return FlowListEntity[]
     */
    public function findFlowsBySource(string $flowSource, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): iterable;

    /**
     * @return FlowListEntity[]
     */
    public function findFlowsByType(string $flowType, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): iterable;

    /**
     * @return FlowListEntity[]
     */
    public function findFlowsBySubject(string $flowSubject, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable;

    /**
     * @return FlowStatsEntity[]
     */
    public function findFlowStats(?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $flowType = null): iterable;

    /**
     * @return FlowTypeStatsEntity[]
     */
    public function findFlowTypeStats(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable;

    public function appendFlow(Flow $flow): void;

    public function appendScheduleException(ScheduleException $scheduleException): void;

    public function truncateFlowList(): void;
}
