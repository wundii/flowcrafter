<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;

class Flow implements JsonSerializable
{
    private string $runtimeHash;

    /**
     * @param class-string $source
     * @param Message[] $messages
     */
    public function __construct(
        private readonly string $type,
        private readonly string $source,
        private readonly FlowSchema $flowSchema,
        private readonly DateTimeImmutable $time,
        private readonly string $hash,
        private ?string $subject = null,
        private array $messages = [],
    ) {
        if (!class_exists($source)) {
            throw new InvalidArgumentException(sprintf(
                'Flow source class "%s" does not exist.',
                $source,
            ));
        }

        if (!is_subclass_of($source, FlowInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Flow source class "%s" does not implement FlowInterface.',
                $source,
            ));
        }

        if (!Assert::isHash($hash)) {
            throw new InvalidArgumentException(sprintf(
                'Hash "%s" is not a valid hash.',
                $hash,
            ));
        }

        if ($type !== $this->flowSchema->type()) {
            throw new InvalidArgumentException(sprintf(
                'Flow type "%s" does not match the schema type "%s".',
                $type,
                $this->flowSchema->type(),
            ));
        }

        $this->runtimeHash = Uuid::uuid7($time)->toString();
    }

    /**
     * @param class-string $source
     */
    public static function create(
        string $type,
        string $source,
        ?string $hash = null,
        ?DateTimeImmutable $time = null,
    ): self {
        $time = $time ?? new DateTimeImmutable();

        return new self(
            type: $type,
            source: $source,
            flowSchema: FlowSchema::create($source),
            time: $time,
            hash: $hash ?? Uuid::uuid7($time)->toString(),
        );
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFlowSchema(): FlowSchema
    {
        return $this->flowSchema;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getRuntimeHash(): string
    {
        return $this->runtimeHash;
    }

    public function getTime(): DateTimeImmutable
    {
        return $this->time;
    }

    public function getLatestMessageTime(): ?DateTimeImmutable
    {
        $messageDates = array_map(
            static fn (Message $message): DateTimeImmutable => $message->getTime(),
            $this->messages,
        );

        if ($messageDates === []) {
            return null;
        }

        return max($messageDates);
    }

    public function getFinishTime(): ?DateTimeImmutable
    {
        $messages = array_filter(
            $this->messages,
            static fn (Message $message): bool => $message->getMessageType() === MessageTypeEnum::FINISH,
        );

        if ($messages === []) {
            return null;
        }

        return current($messages)->getTime();
    }

    /**
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function addMessage(Message $message): void
    {
        $this->messages[] = $message;
    }

    public function getFlowHash(): string
    {
        return $this->flowSchema->getHash();
    }

    /**
     * @return array<string, null|string|array<Message>|FlowSchema>
     */
    public function jsonSerialize(): array
    {
        return [
            'source' => $this->source,
            'subject' => $this->subject,
            'type' => $this->type,
            'flowSchema' => $this->flowSchema,
            'flowHash' => $this->flowSchema->getHash(),
            'hash' => $this->hash,
            'runtimeHash' => $this->runtimeHash,
            'time' => $this->time->format(DateTimeInterface::ATOM),
            'messages' => $this->messages,
        ];
    }
}
