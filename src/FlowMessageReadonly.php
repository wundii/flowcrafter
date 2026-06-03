<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use InvalidArgumentException;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class FlowMessageReadonly extends FlowMessage
{
    /**
     * @param class-string<StepInterface> $stepSource
     * @param class-string<MessageInterface> $messageSource
     */
    public function __construct(
        string $flowHash,
        string $flowRuntimeHash,
        string $flowType,
        string $stepSource,
        string $stepHash,
        MessageTypeEnum $messageTypeEnum,
        string $messageSource,
        string $messageHash,
        MessageInterface $message,
        DateTimeImmutable $time,
        string $hash,
        ?string $predecessorHash = null,
        bool $skipClassValidation = false,
    ) {
        if (!$message instanceof ReadonlyMessage) {
            throw new InvalidArgumentException('Message must be an instance of ReadonlyMessage');
        }

        parent::__construct(
            $flowHash,
            $flowRuntimeHash,
            $flowType,
            $stepSource,
            $stepHash,
            $messageTypeEnum,
            $messageSource,
            $messageHash,
            $message,
            $time,
            $hash,
            $predecessorHash,
            $skipClassValidation,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function createFromArray(array $data): self
    {
        /** @var class-string<StepInterface> $stepSource */
        $stepSource = is_string($data['stepSource'] ?? null) ? $data['stepSource'] : '';
        /** @var class-string<MessageInterface> $messageSource */
        $messageSource = is_string($data['messageSource'] ?? null) ? $data['messageSource'] : '';
        $messageData = is_array($data['message'] ?? null) ? $data['message'] : [];
        $time = is_string($data['time'] ?? null) ? $data['time'] : 'now';

        return new self(
            flowHash: is_string($data['flowHash'] ?? null) ? $data['flowHash'] : '',
            flowRuntimeHash: is_string($data['flowRuntimeHash'] ?? null) ? $data['flowRuntimeHash'] : '',
            flowType: is_string($data['flowType'] ?? null) ? $data['flowType'] : '',
            stepSource: $stepSource,
            stepHash: is_string($data['stepHash'] ?? null) ? $data['stepHash'] : '',
            messageTypeEnum: MessageTypeEnum::from(is_string($data['messageType'] ?? null) ? $data['messageType'] : MessageTypeEnum::WAIT->value),
            messageSource: $messageSource,
            messageHash: is_string($data['messageHash'] ?? null) ? $data['messageHash'] : '',
            message: new ReadonlyMessage($messageSource, $messageData),
            time: new DateTimeImmutable($time),
            hash: is_string($data['hash'] ?? null) ? $data['hash'] : '',
            predecessorHash: is_string($data['predecessorHash'] ?? null) ? $data['predecessorHash'] : null,
            skipClassValidation: true,
        );
    }

    public function getMessage(): ReadonlyMessage
    {
        $message = parent::getMessage();

        if (!$message instanceof ReadonlyMessage) {
            return new ReadonlyMessage(
                originalClass: parent::getMessageSource(),
                rawData: $message->jsonSerialize() ?? [],
            );
        }

        return $message;
    }
}
