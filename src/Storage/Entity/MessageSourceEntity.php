<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;
use JsonSerializable;
use Wundii\Flowcrafter\Interface\MessageInterface;

class MessageSourceEntity implements JsonSerializable
{
    /**
     * @param class-string<MessageInterface> $messageSource
     * @param array<string, list<string>> $propertyNames
     */
    public function __construct(
        public string $messageHash,
        public string $messageSource,
        public array $propertyNames,
        public DateTimeInterface $time,
    ) {
    }

    /**
     * @return array<string, array<string, list<string>>|string>
     */
    public function jsonSerialize(): array
    {
        return [
            'messageHash' => $this->messageHash,
            'messageSource' => $this->messageSource,
            'propertyNames' => $this->propertyNames,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
