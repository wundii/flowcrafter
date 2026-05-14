<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Schedule;

use RuntimeException;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\ScheduleInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;

abstract class AbstractSchedule implements ScheduleInterface
{
    private ?StorageInterface $storage = null;

    /**
     * @var array<class-string|object>
     */
    private array $dependenciesInjection = [];

    /**
     * @param array<class-string|object> $dependenciesInjection
     */
    public function setContext(StorageInterface $storage, array $dependenciesInjection): void
    {
        $this->storage = $storage;
        $this->dependenciesInjection = $dependenciesInjection;
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
        $storage = $this->requireStorage();
        $schema = $flowSource::schema();

        $storage->appendObserveItem(
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
            dependenciesInjection: $this->dependenciesInjection,
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
}
