<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Projection;

use Closure;
use RuntimeException;
use Throwable;
use Wundii\Flowcrafter\FlowContainerFactory;
use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;

final class ProjectionWorker
{
    /**
     * @var array<string, ProjectionHandlerMeta> flowType => meta
     */
    private array $flowTypeToMeta = [];

    /**
     * @var array<class-string<ProjectionHandlerInterface>, ProjectionHandlerInterface>
     */
    private array $handlerInstances = [];

    /**
     * @param ProjectionHandlerMeta[] $projectionHandlerMetas
     * @param array<int|class-string, class-string|object> $dependenciesInjection
     */
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly QueueInterface $queue,
        private readonly array $projectionHandlerMetas,
        private readonly array $dependenciesInjection = [],
    ) {
        foreach ($this->projectionHandlerMetas as $projectionHandlerMeta) {
            foreach ($projectionHandlerMeta->flowTypes as $flowType) {
                $this->flowTypeToMeta[$flowType] = $projectionHandlerMeta;
            }
        }
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
     * Drain the single projection queue for one round, message by message.
     * For each item we resolve the (unique) handler for the message's flow type
     * and the method bound to the message source via #[FlowProjectionMessage].
     * Items without a matching handler or method are acked and skipped. On a
     * handler error we log a ProjectionException, ack the item anyway and move
     * on (log & continue: a single broken handler must not block the shared
     * queue; handlers should still be idempotent).
     *
     * @param (Closure(string): void)|null $logger
     */
    public function tick(?Closure $logger = null, float $maxExecutionTime = 1.0): int
    {
        $projected = 0;

        foreach ($this->queue->observeProjectionQueue($maxExecutionTime) as $projectionQueueItem) {
            $flowMessage = $projectionQueueItem->getFlowMessageReadonly();

            $meta = $this->flowTypeToMeta[$flowMessage->getFlowType()] ?? null;
            if (!$meta instanceof ProjectionHandlerMeta) {
                $this->queue->ackProjectionQueueItem($projectionQueueItem->getItemId());
                continue;
            }

            $method = $meta->messageMethods[$flowMessage->getMessageSource()] ?? null;
            if ($method === null) {
                $this->queue->ackProjectionQueueItem($projectionQueueItem->getItemId());
                continue;
            }

            try {
                $handler = $this->resolveHandler($meta->handlerClass);
                $handler->{$method}($flowMessage);

                $this->queue->ackProjectionQueueItem($projectionQueueItem->getItemId());
                ++$projected;

                if ($logger instanceof Closure) {
                    $logger(sprintf(
                        '%s - Projected: %s::%s (%s)',
                        date('Y-m-d H:i:s'),
                        $meta->handlerClass,
                        $method,
                        $flowMessage->getMessageSource(),
                    ));
                }
            } catch (Throwable $throwable) {
                $this->storage->appendProjectionException(
                    ProjectionException::create(
                        flowHash: $flowMessage->getFlowHash(),
                        flowType: $flowMessage->getFlowType(),
                        projectionHandlerClass: $meta->handlerClass,
                        code: (string) $throwable->getCode(),
                        message: $throwable->getMessage(),
                        file: $throwable->getFile(),
                        line: $throwable->getLine(),
                        traceString: $throwable->getTraceAsString(),
                    )
                );

                $this->queue->ackProjectionQueueItem($projectionQueueItem->getItemId());

                if ($logger instanceof Closure) {
                    $logger(sprintf(
                        '%s - Projection error [%s::%s]: %s',
                        date('Y-m-d H:i:s'),
                        $meta->handlerClass,
                        $method,
                        $throwable->getMessage(),
                    ));
                }
            }
        }

        return $projected;
    }

    /**
     * @param class-string<ProjectionHandlerInterface> $handlerClass
     */
    private function resolveHandler(string $handlerClass): ProjectionHandlerInterface
    {
        if (array_key_exists($handlerClass, $this->handlerInstances)) {
            return $this->handlerInstances[$handlerClass];
        }

        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [$handlerClass],
            dependencies: $this->dependenciesInjection,
        );
        $handler = $containerBuilder->get($handlerClass);
        if (!$handler instanceof ProjectionHandlerInterface) {
            throw new RuntimeException(sprintf(
                'Projection handler "%s" could not be instantiated as ProjectionHandlerInterface.',
                $handlerClass,
            ));
        }

        $this->handlerInstances[$handlerClass] = $handler;

        return $handler;
    }
}
