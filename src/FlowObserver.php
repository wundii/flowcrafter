<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Closure;
use DateTime;
use DateTimeInterface;
use RuntimeException;
use Wundii\DataMapper\DataConfig;
use Wundii\DataMapper\DataMapper;
use Wundii\DataMapper\Enum\ApproachEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;

readonly class FlowObserver
{
    public function __construct(
        private StorageInterface $storage,
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
    ): void {
        $dataConfig = new DataConfig(
            approachEnum: ApproachEnum::CONSTRUCTOR,
            classMap: $messageClassMap,
        );
        $dataMapper = new DataMapper($dataConfig);

        foreach ($this->storage->observeQueue($maxExecutionTimeInSeconds) as $observeItem) {
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

            $message = $dataMapper->array($observeItem->getMessage(), $messageSource);
            if (!$message instanceof MessageInterface) {
                throw new RuntimeException('Mapped message does not implement MessageInterface.');
            }

            $flowRunner = new FlowRunner(
                type: $observeItem->getType(),
                flowSource: $observeItem->getFlowSource(),
                storage: $this->storage,
            );

            $flowRunner->run(
                message: $message,
                flowHash: $observeItem->getFlowHash(),
                queueId: $observeItem->getQueueId(),
            );
        }
    }
}
