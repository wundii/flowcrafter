<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Projection;

use Closure;
use RuntimeException;
use Throwable;
use Wundii\Flowcrafter\FlowContainerFactory;
use Wundii\Flowcrafter\FlowMessageReadonly;
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
        private readonly ?StorageInterface $storage,
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
     * @param (Closure(string): void)|null $logger
     * @param (Closure(ProjectionResult): void)|null $reporter
     */
    public function tick(?Closure $logger = null, float $maxExecutionTime = 1.0, ?Closure $reporter = null): int
    {
        $projected = 0;
        $capture = $reporter instanceof Closure;

        foreach ($this->queue->observeProjectionQueue($maxExecutionTime) as $projectionQueueItem) {
            $flowMessage = $projectionQueueItem->getFlowMessageReadonly();
            $meta = $this->flowTypeToMeta[$flowMessage->getFlowType()] ?? null;
            $method = $meta instanceof ProjectionHandlerMeta
                ? ($meta->messageMethods[$flowMessage->getMessageSource()] ?? null)
                : null;

            if (!$meta instanceof ProjectionHandlerMeta || $method === null) {
                $this->queue->ackProjectionQueueItem($projectionQueueItem->getItemId());
                continue;
            }

            [$content, $throwable] = $this->invokeHandler($meta, $method, $flowMessage, $capture);
            $this->queue->ackProjectionQueueItem($projectionQueueItem->getItemId());

            $projected += $this->handleOutcome($meta, $method, $flowMessage, $throwable, $logger);

            if ($reporter instanceof Closure) {
                $reporter(new ProjectionResult(
                    handlerClass: $meta->handlerClass,
                    method: $method,
                    messageSource: $flowMessage->getMessageSource(),
                    content: $content,
                    throwable: $throwable,
                ));
            }
        }

        return $projected;
    }

    /**
     * @param (Closure(string): void)|null $logger
     */
    private function handleOutcome(
        ProjectionHandlerMeta $projectionHandlerMeta,
        string $method,
        FlowMessageReadonly $flowMessageReadonly,
        ?Throwable $throwable,
        ?Closure $logger,
    ): int {
        if ($throwable instanceof Throwable) {
            $this->storage?->appendProjectionException(
                ProjectionException::create(
                    flowHash: $flowMessageReadonly->getFlowHash(),
                    flowType: $flowMessageReadonly->getFlowType(),
                    projectionHandlerClass: $projectionHandlerMeta->handlerClass,
                    code: (string) $throwable->getCode(),
                    message: $throwable->getMessage(),
                    file: $throwable->getFile(),
                    line: $throwable->getLine(),
                    traceString: $throwable->getTraceAsString(),
                )
            );

            $this->log($logger, sprintf(
                '%s - Projection error [%s::%s]: %s',
                date('Y-m-d H:i:s'),
                $projectionHandlerMeta->handlerClass,
                $method,
                $throwable->getMessage(),
            ));

            return 0;
        }

        $this->log($logger, sprintf(
            '%s - Projected: %s::%s (%s)',
            date('Y-m-d H:i:s'),
            $projectionHandlerMeta->handlerClass,
            $method,
            $flowMessageReadonly->getMessageSource(),
        ));

        return 1;
    }

    /**
     * @param (Closure(string): void)|null $logger
     */
    private function log(?Closure $logger, string $message): void
    {
        if (!$logger instanceof Closure) {
            return;
        }

        $logger($message);
    }

    /**
     * Resolve and invoke the bound handler method for a single message.
     * Returns the captured stdout (empty unless $capture is true) and the
     * thrown error, if any, so the caller can log/persist/report uniformly.
     *
     * @return array{0: string, 1: ?Throwable}
     */
    private function invokeHandler(ProjectionHandlerMeta $projectionHandlerMeta, string $method, FlowMessageReadonly $flowMessageReadonly, bool $capture): array
    {
        $throwable = null;

        if ($capture) {
            ob_start();
        }

        try {
            $handler = $this->resolveHandler($projectionHandlerMeta->handlerClass);
            $handler->{$method}($flowMessageReadonly);
        } catch (Throwable $caught) {
            $throwable = $caught;
        }

        $content = '';
        if ($capture) {
            $buffer = ob_get_clean();
            $content = $buffer === false ? '' : $buffer;
        }

        return [$content, $throwable];
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
