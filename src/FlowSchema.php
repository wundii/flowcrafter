<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use JsonSerializable;
use RuntimeException;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class FlowSchema implements JsonSerializable
{
    private ?string $hash = null;

    /**
     * @var array<class-string<MessageInterface>, Step[]>|null
     */
    private ?array $messageToStepsMap = null;

    /**
     * @var Step[]|null
     */
    private ?array $leafSteps = null;

    /**
     * @param Step[] $steps
     */
    public function __construct(
        private readonly string $type,
        private readonly array $steps,
    ) {
    }

    /**
     * @param class-string<FlowInterface> $class
     */
    public static function create(string $class): self
    {
        Assert::classString(
            $class,
            FlowInterface::class,
            sprintf('Class "%s" does not implement FlowInterface.', $class),
        );

        return $class::schema();
    }

    public function type(): string
    {
        return $this->type;
    }

    public function initStep(): Step
    {
        $steps = array_filter(
            $this->steps,
            static fn (Step $step): bool => $step->getMessageEnum() === MessageEnum::INIT,
        );

        return reset($steps) ?: throw new RuntimeException('No INIT step found in the schema.');
    }

    /**
     * @return Step[]
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * @return Step[]
     */
    public function stepByMessageClass(string $messageClass): array
    {
        Assert::classString(
            $messageClass,
            MessageInterface::class,
            sprintf('Class "%s" does not implement MessageInterface.', $messageClass),
        );

        $steps = array_filter(
            $this->steps,
            static fn (Step $step): bool => in_array($messageClass, $step->getMessages(), true)
        );

        return array_values($steps);
    }

    /**
     * @param class-string<StepInterface> $stepSource
     */
    public function stepBySource(string $stepSource): Step
    {
        Assert::classString(
            $stepSource,
            StepInterface::class,
            sprintf('Class "%s" does not implement StepInterface.', $stepSource),
        );

        foreach ($this->steps as $step) {
            if ($step->getSource() === $stepSource) {
                return $step;
            }
        }

        throw new RuntimeException(sprintf('No step found with source "%s".', $stepSource));
    }

    /**
     * @return array<class-string<MessageInterface>, Step[]>
     */
    public function getMessageToStepsMap(): array
    {
        if ($this->messageToStepsMap !== null) {
            return $this->messageToStepsMap;
        }

        $map = [];

        foreach ($this->steps as $step) {
            foreach ($step->getMessages() as $messageClass) {
                $map[$messageClass][] = $step;
            }
        }

        $this->messageToStepsMap = $map;

        return $map;
    }

    public function getHash(): string
    {
        if ($this->hash !== null) {
            return $this->hash;
        }

        $json = json_encode($this->jsonSerialize());
        if ($json === false) {
            throw new RuntimeException('Failed to encode flow schema to JSON.');
        }

        $this->hash = md5($json);

        return $this->hash;
    }

    /**
     * @return Step[]
     */
    public function getLeafSteps(): array
    {
        if ($this->leafSteps !== null) {
            return $this->leafSteps;
        }

        $allMessages = [];

        foreach ($this->steps as $step) {
            foreach ($step->getMessages() as $message) {
                $allMessages[] = $message;
            }
        }

        $this->leafSteps = array_values(array_filter($this->steps, function (Step $step) use ($allMessages): bool {
            foreach ($step->getReturnTypes() as $returnType) {
                if (in_array($returnType, $allMessages, true)) {
                    return false;
                }
            }

            return true;
        }));

        return $this->leafSteps;
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'steps' => $this->steps,
        ];
    }
}
