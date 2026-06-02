<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Schedule;

use RuntimeException;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\ScheduleInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Projection\ProjectionHandlerMeta;

abstract class AbstractSchedule implements ScheduleInterface
{
    private ?StorageInterface $storage = null;

    private ?QueueInterface $queue = null;

    /**
     * @var array<class-string|object>
     */
    private array $dependenciesInjection = [];

    /**
     * @var ProjectionHandlerMeta[]
     */
    private array $projectionHandlerMetas = [];

    /**
     * @param array<class-string|object> $dependenciesInjection
     * @param ProjectionHandlerMeta[] $projectionHandlerMetas
     */
    public function setContext(StorageInterface $storage, QueueInterface $queue, array $dependenciesInjection, array $projectionHandlerMetas = []): void
    {
        $this->storage = $storage;
        $this->queue = $queue;
        $this->dependenciesInjection = $dependenciesInjection;
        $this->projectionHandlerMetas = $projectionHandlerMetas;
    }

    /**
     * @param class-string<FlowInterface> $flowSource
     * @param class-string[] $includeSteps
     */
    protected function enqueue(
        string $flowSource,
        MessageInterface $message,
        ?string $flowHash = null,
        ?string $flowSubject = null,
        array $includeSteps = [],
    ): void {
        $queue = $this->requireQueue();
        $schema = $flowSource::schema();

        $queue->appendObserveItem(
            type: $schema->type(),
            flowSource: $flowSource,
            flowHash: $flowHash,
            messageSource: get_class($message),
            message: $message->jsonSerialize(),
            includeSteps: $includeSteps,
            flowSubject: $flowSubject,
        );
    }

    /**
     * @param class-string<FlowInterface> $flowSource
     */
    protected function run(
        string $flowSource,
        MessageInterface $message,
        ?string $flowHash = null,
        ?string $flowSubject = null,
    ): bool|MessageReturnInterface {
        $schema = $flowSource::schema();

        $flowRunner = new FlowRunner(
            type: $schema->type(),
            flowSource: $flowSource,
            flowSubject: $flowSubject,
            storage: $this->requireStorage(),
            queue: $this->requireQueue(),
            dependenciesInjection: $this->dependenciesInjection,
            projectionHandlerMetas: $this->projectionHandlerMetas,
        );

        return $flowRunner->run(
            message: $message,
            flowHash: $flowHash,
        );
    }

    private function requireStorage(): StorageInterface
    {
        if (!$this->storage instanceof StorageInterface) {
            throw new RuntimeException('Schedule context not initialized. Call setContext() before process().');
        }

        return $this->storage;
    }

    private function requireQueue(): QueueInterface
    {
        if (!$this->queue instanceof QueueInterface) {
            throw new RuntimeException('Schedule context not initialized. Call setContext() before process().');
        }

        return $this->queue;
    }
}
