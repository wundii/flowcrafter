<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class Flow implements JsonSerializable
{
    private string $flowRuntimeHash;

    /**
     * @param class-string<FlowInterface> $flowSource
     * @param FlowMessage[] $flowMessages
     */
    public function __construct(
        private readonly string $flowType,
        private readonly string $flowSource,
        private readonly FlowSchema $flowSchema,
        private readonly DateTimeImmutable $time,
        private readonly string $flowHash,
        private readonly ?string $flowSubject = null,
        private array $flowMessages = [],
    ) {
        Assert::classString(
            $flowSource,
            FlowInterface::class,
            sprintf('Flow source class "%s" does not implement FlowInterface.', $flowSource),
        );

        if (!Assert::isHash($flowHash)) {
            throw new InvalidArgumentException(sprintf(
                'Hash "%s" is not a valid hash.',
                $flowHash,
            ));
        }

        if ($flowType !== $this->flowSchema->type()) {
            throw new InvalidArgumentException(sprintf(
                'Flow type "%s" does not match the schema type "%s".',
                $flowType,
                $this->flowSchema->type(),
            ));
        }

        $this->flowRuntimeHash = Uuid::uuid7($time)->toString();
    }

    /**
     * @param class-string<FlowInterface> $flowSource
     */
    public static function create(
        string $flowType,
        string $flowSource,
        ?string $flowSubject = null,
        ?string $flowHash = null,
        ?DateTimeImmutable $time = null,
    ): self {
        $time = $time ?? new DateTimeImmutable();

        return new self(
            flowType: $flowType,
            flowSource: $flowSource,
            flowSchema: FlowSchema::create($flowSource),
            time: $time,
            flowHash: $flowHash ?? Uuid::uuid7($time)->toString(),
            flowSubject: $flowSubject,
        );
    }

    /**
     * @return class-string<FlowInterface>
     */
    public function getSource(): string
    {
        return $this->flowSource;
    }

    public function getSubject(): ?string
    {
        return $this->flowSubject;
    }

    public function getType(): string
    {
        return $this->flowType;
    }

    public function getSchema(): FlowSchema
    {
        return $this->flowSchema;
    }

    public function getHash(): string
    {
        return $this->flowHash;
    }

    public function getRuntimeHash(): string
    {
        return $this->flowRuntimeHash;
    }

    public function getTime(): DateTimeImmutable
    {
        return $this->time;
    }

    public function getLatestMessageTime(): ?DateTimeImmutable
    {
        $messageDates = array_map(
            static fn (FlowMessage $flowMessage): DateTimeImmutable => $flowMessage->getTime(),
            $this->flowMessages,
        );

        if ($messageDates === []) {
            return null;
        }

        return max($messageDates);
    }

    public function getFinishTime(): ?DateTimeImmutable
    {
        $messages = array_filter(
            $this->flowMessages,
            static fn (FlowMessage $flowMessage): bool => $flowMessage->getMessageType() === MessageTypeEnum::FINISH,
        );

        if ($messages === []) {
            return null;
        }

        if (count($messages) > 1) {
            throw new InvalidArgumentException('Multiple FINISH messages found in the flow.');
        }

        return current($messages)->getTime();
    }

    /**
     * @return FlowMessage[]
     */
    public function getFlowMessages(): array
    {
        return $this->flowMessages;
    }

    public function addMessage(FlowMessage $flowMessage): void
    {
        $this->flowMessages[] = $flowMessage;
    }

    /**
     * @param class-string<MessageInterface> $message
     */
    public function hasMessage(string $message): bool
    {
        Assert::classString($message, MessageInterface::class);

        foreach ($this->flowMessages as $flowMessage) {
            if ($flowMessage->getMessage() instanceof $message) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string<StubInterface> $stubSource
     * @return array<MessageInterface>
     */
    public function executableMessages(string $stubSource): array
    {
        Assert::classString($stubSource, StubInterface::class);

        $flowMessages = array_filter(
            $this->flowMessages,
            static fn (FlowMessage $flowMessage): bool => $flowMessage->getStubSource() === $stubSource
                && $flowMessage->getMessageType() === MessageTypeEnum::WAIT,
        );

        $stub = $this->flowSchema->stubBySource($stubSource);
        $stubMessageClasses = $stub->getMessages();
        $flowMessageClasses = array_map(
            static fn (FlowMessage $flowMessage): string => $flowMessage->getMessageSource(),
            $flowMessages,
        );

        sort($stubMessageClasses);
        sort($flowMessageClasses);

        if ($stubMessageClasses !== $flowMessageClasses) {
            return [];
        }

        foreach ($flowMessages as $flowMessage) {
            $flowMessage->setProcess();
        }

        return array_map(
            static fn (FlowMessage $flowMessage): MessageInterface => $flowMessage->getMessage(),
            $flowMessages,
        );
    }

    public function getSchemaHash(): string
    {
        return $this->flowSchema->getHash();
    }

    /**
     * @return array<string, null|string|array<FlowMessage>|FlowSchema>
     */
    public function jsonSerialize(): array
    {
        return [
            'flowSource' => $this->flowSource,
            'flowSubject' => $this->flowSubject,
            'flowType' => $this->flowType,
            'flowSchema' => $this->flowSchema,
            'flowSchemaHash' => $this->flowSchema->getHash(),
            'flowHash' => $this->flowHash,
            'flowRuntimeHash' => $this->flowRuntimeHash,
            'time' => $this->time->format(DateTimeInterface::ATOM),
            'flowMessages' => $this->flowMessages,
        ];
    }
}
