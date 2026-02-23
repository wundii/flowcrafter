<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\DataMapper\DataConfig;
use Wundii\DataMapper\DataMapper;
use Wundii\DataMapper\Enum\ApproachEnum;
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
     */
    public function run(
        array $messageClassMap = [],
    ): void {
        $this->storage->initializeDatabase();

        $dataConfig = new DataConfig(
            approachEnum: ApproachEnum::CONSTRUCTOR,
            classMap: $messageClassMap,
        );
        $dataMapper = new DataMapper($dataConfig);

        foreach ($this->storage->observeQueue() as $observeItem) {
            $messageSource = Assert::classString($observeItem->getMessageSource(), MessageInterface::class, 'Each Message must have a string source.');

            $message = $dataMapper->array($observeItem->getMessage(), $messageSource);
            if (!$message instanceof MessageInterface) {
                throw new \RuntimeException('Mapped message does not implement MessageInterface.');
            }

            $flowRunner = new FlowRunner(
                type: $observeItem->getType(),
                flowSource: $observeItem->getFlowSource(),
                storage: $this->storage,
            );

            $flowRunner->run(
                message: $message,
                flowHash: $observeItem->getFlowSHash(),
                queueId: $observeItem->getQueueId(),
            );
        }
    }
}
