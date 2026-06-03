<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Closure;
use DateTime;
use DateTimeInterface;
use Throwable;
use Wundii\DataMapper\DataConfig;
use Wundii\DataMapper\DataMapper;
use Wundii\DataMapper\Enum\ApproachEnum;
use Wundii\Flowcrafter\Console\Heartbeat;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Projection\ProjectionHandlerMeta;

final readonly class FlowObserver
{
    /**
     * @param array<class-string|object> $dependenciesInjection
     * @param ProjectionHandlerMeta[] $projectionHandlerMetas
     */
    public function __construct(
        private StorageInterface $storage,
        private QueueInterface $queue,
        private array $dependenciesInjection,
        private array $projectionHandlerMetas = [],
    ) {
    }

    /**
     * @param array<class-string, class-string> $messageClassMap
     * @param (Closure(string): void)|null $logger
     */
    public function run(
        array $messageClassMap = [
            DateTimeInterface::class => DateTime::class,
        ],
        float $maxExecutionTimeInSeconds = 0.0,
        ?Closure $logger = null,
        ?Heartbeat $heartbeat = null,
    ): void {
        $dataConfig = new DataConfig(
            approachEnum: ApproachEnum::CONSTRUCTOR,
            classMap: $messageClassMap,
        );
        $dataMapper = new DataMapper($dataConfig);

        $flowRunner = null;
        $observeItem = null;

        try {
            foreach ($this->queue->observeQueue($maxExecutionTimeInSeconds) as $observeItem) {
                $flowSource = Assert::classString($observeItem->getFlowSource(), FlowInterface::class, 'Each Flow must have a string source.');
                $messageSource = Assert::classString($observeItem->getMessageSource(), MessageInterface::class, 'Each Message must have a string source.');

                if ($logger instanceof \Closure) {
                    $logger(sprintf(
                        '%s - Flow: %s - Message: %s',
                        date('Y-m-d H:i:s'),
                        $flowSource,
                        $messageSource,
                    ));
                }

                /** @var MessageInterface $message */
                $message = $dataMapper->array($observeItem->getMessage() ?? [], $messageSource);

                $flowRunner = new FlowRunner(
                    type: $observeItem->getType(),
                    flowSource: $observeItem->getFlowSource(),
                    flowSubject: $observeItem->getFlowSubject(),
                    storage: $this->storage,
                    queue: $this->queue,
                    dependenciesInjection: $this->dependenciesInjection,
                    projectionHandlerMetas: $this->projectionHandlerMetas,
                );

                $flowRunner->run(
                    message: $message,
                    flowHash: $observeItem->getFlowHash(),
                    queueId: $observeItem->getQueueId(),
                    includeSteps: $observeItem->getIncludeSteps(),
                );

                $heartbeat?->touchIfDue();

                $flowRunner = null;
                $observeItem = null;
            }
        } catch (Throwable $throwable) {
            $flowExceptions = $flowRunner?->getFlow()?->getFlowExceptions() ?? [];

            if ($flowExceptions === []) {
                $this->storage->appendObserverException(ObserverException::create(
                    flowSource: $observeItem?->getFlowSource() ?? '',
                    messageSource: $observeItem?->getMessageSource() ?? '',
                    queueId: $observeItem?->getQueueId(),
                    code: (string) $throwable->getCode(),
                    message: $throwable->getMessage(),
                    file: $throwable->getFile(),
                    line: $throwable->getLine(),
                    traceString: $throwable->getTraceAsString(),
                ));
            }

            throw $throwable;
        }
    }
}
