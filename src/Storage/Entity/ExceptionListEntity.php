<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use DateTimeInterface;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\StatusEnum;

class ExceptionListEntity implements JsonSerializable
{
    public function __construct(
        public string $type,
        public string $hash,
        public int $code,
        public string $message,
        public string $file,
        public int $line,
        public string $traceString,
        public DateTimeInterface $time,
        public ?string $flowHash,
        public ?string $flowRuntimeHash,
        public ?string $flowType,
        public ?string $stepSource,
        public ?string $stepHash,
        public ?StatusEnum $flowStatus,
        public ?string $scheduleClass,
        public ?string $scheduleName,
        public ?string $scheduleExpression,
        public ?string $observerFlowSource = null,
        public ?string $observerMessageSource = null,
        public ?string $observerQueueId = null,
        public ?string $projectionHandlerClass = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'type' => $this->type,
            'hash' => $this->hash,
            'code' => $this->code,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'traceString' => $this->traceString,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
        ];

        if ($this->type === 'flow') {
            $data['flowHash'] = $this->flowHash;
            $data['flowRuntimeHash'] = $this->flowRuntimeHash;
            $data['flowType'] = $this->flowType;
            $data['stepSource'] = $this->stepSource;
            $data['stepHash'] = $this->stepHash;
            $data['flowStatus'] = $this->flowStatus?->name;
        }

        if ($this->type === 'schedule') {
            $data['scheduleClass'] = $this->scheduleClass;
            $data['scheduleName'] = $this->scheduleName;
            $data['scheduleExpression'] = $this->scheduleExpression;
        }

        if ($this->type === 'observer') {
            $data['observerFlowSource'] = $this->observerFlowSource;
            $data['observerMessageSource'] = $this->observerMessageSource;
            $data['observerQueueId'] = $this->observerQueueId;
        }

        if ($this->type === 'projection') {
            $data['flowHash'] = $this->flowHash;
            $data['flowType'] = $this->flowType;
            $data['projectionHandlerClass'] = $this->projectionHandlerClass;
        }

        return $data;
    }
}
