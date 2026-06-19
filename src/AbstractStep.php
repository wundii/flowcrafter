<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use ReflectionException;
use RuntimeException;
use Throwable;
use Wundii\Flowcrafter\DependencyInjection\DependencyRegistry;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StepInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Projection\ProjectionHandlerMeta;

abstract class AbstractStep implements StepInterface
{
    private string $flowHash;

    private string $flowRuntimeHash;

    private string $flowType;

    private string $flowSchemaHash;

    private ?string $flowSubject;

    private ?StorageInterface $storage = null;

    private ?QueueInterface $queue = null;

    private ?DependencyRegistry $dependencyRegistry = null;

    /**
     * @var ProjectionHandlerMeta[]
     */
    private array $projectionHandlerMetas = [];

    /**
     * @param ProjectionHandlerMeta[] $projectionHandlerMetas
     */
    public function setAbstractData(
        string $flowHash,
        string $flowRuntimeHash,
        string $flowType,
        string $flowSchemaHash,
        ?string $flowSubject,
        ?StorageInterface $storage = null,
        ?QueueInterface $queue = null,
        ?DependencyRegistry $dependencyRegistry = null,
        array $projectionHandlerMetas = [],
    ): void {
        $this->flowHash = $flowHash;
        $this->flowRuntimeHash = $flowRuntimeHash;
        $this->flowType = $flowType;
        $this->flowSchemaHash = $flowSchemaHash;
        $this->flowSubject = $flowSubject;
        $this->storage = $storage;
        $this->queue = $queue;
        $this->dependencyRegistry = $dependencyRegistry;
        $this->projectionHandlerMetas = $projectionHandlerMetas;
    }

    public function getFlowHash(): string
    {
        return $this->flowHash;
    }

    public function getFlowRuntimeHash(): string
    {
        return $this->flowRuntimeHash;
    }

    public function getFlowType(): string
    {
        return $this->flowType;
    }

    public function getFlowSchemaHash(): string
    {
        return $this->flowSchemaHash;
    }

    public function getFlowSubject(): ?string
    {
        return $this->flowSubject;
    }

    /**
     * Trigger another flow asynchronously by appending it to the observe queue.
     *
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
        if (!$this->queue instanceof QueueInterface) {
            throw new RuntimeException('Queue is not set.');
        }

        $this->queue->appendObserveItem(
            type: $flowSource::schema()->type(),
            flowSource: $flowSource,
            flowHash: $flowHash,
            messageSource: get_class($message),
            message: $message->jsonSerialize(),
            includeSteps: $includeSteps,
            flowSubject: $flowSubject,
        );
    }

    /**
     * Trigger another flow synchronously via a fresh FlowRunner.
     *
     * @param class-string<FlowInterface> $flowSource
     * @throws ReflectionException
     * @throws Throwable
     */
    protected function run(
        string $flowSource,
        MessageInterface $message,
        ?string $flowHash = null,
        ?string $flowSubject = null,
    ): bool|MessageReturnInterface {
        $flowRunner = new FlowRunner(
            type: $flowSource::schema()->type(),
            flowSource: $flowSource,
            flowSubject: $flowSubject,
            storage: $this->storage,
            queue: $this->queue,
            dependencyRegistry: $this->dependencyRegistry ?? new DependencyRegistry(),
            projectionHandlerMetas: $this->projectionHandlerMetas,
        );

        return $flowRunner->run(
            message: $message,
            flowHash: $flowHash,
        );
    }
}
