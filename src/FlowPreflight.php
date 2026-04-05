<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;
use Wundii\DataMapper\DataConfig;
use Wundii\DataMapper\DataMapper;
use Wundii\DataMapper\Enum\ApproachEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;

final readonly class FlowPreflight
{
    /**
     * @param array<class-string, class-string> $messageClassMap
     */
    public function __construct(
        private array $messageClassMap = [
            DateTimeInterface::class => DateTime::class,
        ],
    ) {
    }

    /**
     * @return class-string<FlowInterface>
     */
    public function ensureFlowSource(string $flowSource): string
    {
        return Assert::classString(
            $flowSource,
            FlowInterface::class,
            sprintf('flowSource "%s" must implement %s.', $flowSource, FlowInterface::class),
        );
    }

    /**
     * @return class-string<AbstractMessage>
     */
    public function ensureMessageSource(string $messageSource): string
    {
        return Assert::classString(
            $messageSource,
            AbstractMessage::class,
            sprintf('messageSource "%s" must extend %s.', $messageSource, AbstractMessage::class),
        );
    }

    /**
     * @param class-string<MessageInterface> $messageSource
     * @param array<mixed> $message
     */
    public function hydrateMessage(string $messageSource, array $message): MessageInterface
    {
        $messageSourceEntity = Source::message($messageSource);
        $shortName = (new ReflectionClass($messageSource))->getShortName();
        $expectedKeys = $messageSourceEntity->propertyNames[$shortName] ?? [];

        $missingKeys = array_diff($expectedKeys, array_keys($message));
        if ($missingKeys !== []) {
            throw new InvalidArgumentException(sprintf(
                'message payload for "%s" is missing required keys: %s. Expected keys: %s.',
                $messageSource,
                implode(', ', $missingKeys),
                implode(', ', $expectedKeys),
            ));
        }

        $dataMapper = new DataMapper(new DataConfig(
            approachEnum: ApproachEnum::CONSTRUCTOR,
            classMap: $this->messageClassMap,
        ));

        try {
            $hydrated = $dataMapper->array($message, $messageSource);
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException(sprintf(
                'message payload does not match %s constructor: %s',
                $messageSource,
                $throwable->getMessage(),
            ), 0, $throwable);
        }

        if (!$hydrated instanceof MessageInterface) {
            throw new InvalidArgumentException(sprintf(
                'hydrated payload does not implement %s.',
                MessageInterface::class,
            ));
        }

        return $hydrated;
    }
}
