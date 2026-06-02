<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Projection;

use Closure;
use Throwable;
use Wundii\Flowcrafter\FlowContainerFactory;
use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;

final readonly class ProjectionWorker
{
    /**
     * @param ProjectionHandlerMeta[] $projectionHandlerMetas
     * @param array<int|class-string, class-string|object> $dependenciesInjection
     */
    public function __construct(
        private StorageInterface $storage,
        private QueueInterface $queue,
        private array $projectionHandlerMetas,
        private array $dependenciesInjection = [],
    ) {
    }

    /**
     * @param (Closure(string): void)|null $logger
     */
    public function run(
        ?Closure $logger = null,
        float $pollIntervalSeconds = 1.0,
    ): void {
        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            $this->tick($logger, $pollIntervalSeconds);
        }
    }

    /**
     * Drain every handler's projection queue for one round. Each handler is
     * polled for up to $maxExecutionTimePerHandler seconds; items are acked
     * after a successful projection. On a handler error we stop draining that
     * handler so the failed item is retried on the next round instead of being
     * skipped (at-least-once, handlers must be idempotent).
     *
     * @param (Closure(string): void)|null $logger
     */
    public function tick(?Closure $logger = null, float $maxExecutionTimePerHandler = 1.0): int
    {
        $projected = 0;

        foreach ($this->projectionHandlerMetas as $projectionHandlerMeta) {
            $projected += $this->processHandler($projectionHandlerMeta, $logger, $maxExecutionTimePerHandler);
        }

        return $projected;
    }

    /**
     * @param (Closure(string): void)|null $logger
     */
    private function processHandler(ProjectionHandlerMeta $projectionHandlerMeta, ?Closure $logger, float $maxExecutionTime): int
    {
        $projected = 0;
        $handler = null;

        foreach ($this->queue->observeProjectionQueue($projectionHandlerMeta->handlerClass, $maxExecutionTime) as $projectionQueueItem) {
            if (!$handler instanceof ProjectionHandlerInterface) {
                $container = FlowContainerFactory::build(
                    autowireClasses: [$projectionHandlerMeta->handlerClass],
                    dependencies: $this->dependenciesInjection,
                );
                $built = $container->get($projectionHandlerMeta->handlerClass);
                if (!$built instanceof ProjectionHandlerInterface) {
                    break;
                }

                $handler = $built;
            }

            $flowMessage = $projectionQueueItem->getFlowMessageReadonly();

            try {
                $handler->project($flowMessage);
                $this->queue->ackProjectionQueueItem($projectionHandlerMeta->handlerClass, $projectionQueueItem->getItemId());
                ++$projected;

                if ($logger instanceof Closure) {
                    $logger(sprintf(
                        '%s - Projected: %s (%s)',
                        date('Y-m-d H:i:s'),
                        $projectionHandlerMeta->handlerClass,
                        $flowMessage->getMessageSource(),
                    ));
                }
            } catch (Throwable $throwable) {
                $this->storage->appendProjectionException(
                    ProjectionException::create(
                        flowHash: $flowMessage->getFlowHash(),
                        flowType: $flowMessage->getFlowType(),
                        projectionHandlerClass: $projectionHandlerMeta->handlerClass,
                        code: $throwable->getCode(),
                        message: $throwable->getMessage(),
                        file: $throwable->getFile(),
                        line: $throwable->getLine(),
                        traceString: $throwable->getTraceAsString(),
                    )
                );

                if ($logger instanceof Closure) {
                    $logger(sprintf(
                        '%s - Projection error [%s]: %s',
                        date('Y-m-d H:i:s'),
                        $projectionHandlerMeta->handlerClass,
                        $throwable->getMessage(),
                    ));
                }

                break;
            }
        }

        return $projected;
    }
}
