<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use InvalidArgumentException;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class FlowBuilder
{
    /**
     * @var Step[]
     */
    private array $steps = [];

    /**
     * @param class-string<MessageInitInterface> $messageInit
     * @param class-string<MessageReturnInterface>|null $messageReturn
     */
    public function __construct(
        private readonly string $type,
        private readonly string $messageInit,
        private readonly ?string $messageReturn = null,
    ) {
        if (!preg_match('/^flow\..+\.v\d+$/', $this->type)) {
            throw new InvalidArgumentException(sprintf(
                'Flow type "%s" must start with "flow." and end with ".v" followed by a number (e.g. "flow.example.v1").',
                $this->type,
            ));
        }

        Assert::classString(
            $this->messageInit,
            MessageEnum::INIT->interface(),
            'Message must be an instance of MessageInitInterface'
        );

        if ($this->messageReturn !== null) {
            Assert::classString(
                $this->messageReturn,
                MessageEnum::RETURN->interface(),
                'Message must be an instance of MessageReturnInterface'
            );
        }
    }

    /**
     * @param class-string<StepInterface> $step
     * @param int<0, max> $retries retries after failure
     * @param int<0, max> $delay in milliseconds
     */
    public function addStep(
        string $step,
        int $retries = Step::DEFAULT_RETRIES,
        int $delay = Step::DEFAULT_DELAY,
        bool $runOnce = false,
    ): void {
        Assert::classString(
            $step,
            StepInterface::class,
            'Step must be an instance of StepInterface'
        );

        foreach ($this->steps as $existing) {
            if ($existing->getSource() === $step) {
                throw new InvalidArgumentException(sprintf(
                    'Step "%s" is already added to the flow.',
                    $step,
                ));
            }
        }

        $this->steps[] = Step::create($step, $retries, $delay, $runOnce);
    }

    public function build(): FlowSchema
    {
        $allMessages = $this->collectAllMessages();

        if (!in_array($this->messageInit, $allMessages, true)) {
            throw new InvalidArgumentException(sprintf(
                'MessageInit "%s" is not added to the flow.',
                $this->messageInit,
            ));
        }

        if ($this->messageReturn !== null) {
            $allReturnTypes = $this->collectAllReturnTypes();

            if (!in_array($this->messageReturn, $allReturnTypes, true)) {
                throw new InvalidArgumentException(sprintf(
                    'MessageReturn "%s" is not added to the flow.',
                    $this->messageReturn,
                ));
            }
        }

        $adjacency = $this->buildAdjacencyMap();
        $this->validateNoLoops($adjacency);
        $this->validateAllStepsConnected($adjacency);
        $this->validateNoDanglingReturnTypes();

        return new FlowSchema($this->type, $this->steps);
    }

    /**
     * @return class-string[]
     */
    private function collectAllMessages(): array
    {
        $messages = [];
        foreach ($this->steps as $step) {
            array_push($messages, ...$step->getMessages());
        }

        return $messages;
    }

    /**
     * @return class-string[]
     */
    private function collectAllReturnTypes(): array
    {
        $returnTypes = [];
        foreach ($this->steps as $step) {
            array_push($returnTypes, ...$step->getReturnTypes());
        }

        return array_unique($returnTypes);
    }

    /**
     * @return array<string, string[]> map of step source class → list of successor step source classes
     */
    private function buildAdjacencyMap(): array
    {
        /** @var array<class-string, string[]> $messageToSteps message class → list of step sources consuming it */
        $messageToSteps = [];
        foreach ($this->steps as $step) {
            foreach ($step->getMessages() as $messageClass) {
                $messageToSteps[$messageClass][] = $step->getSource();
            }
        }

        $adjacency = [];
        foreach ($this->steps as $step) {
            $source = $step->getSource();
            $adjacency[$source] = [];

            foreach ($step->getReturnTypes() as $returnType) {
                foreach ($messageToSteps[$returnType] ?? [] as $consumerSource) {
                    $adjacency[$source][] = $consumerSource;
                }
            }
        }

        return $adjacency;
    }

    /**
     * @param array<string, string[]> $adjacency
     */
    private function validateNoLoops(array $adjacency): void
    {
        /** @var array<string, string> $color white=unvisited, gray=in-stack, black=done */
        $color = [];
        foreach (array_keys($adjacency) as $node) {
            $color[$node] = 'white';
        }

        /** @var string[] $cyclePath */
        $cyclePath = [];

        foreach (array_keys($adjacency) as $node) {
            if ($color[$node] === 'white' && $this->hasCycleDfs($node, $adjacency, $color, $cyclePath)) {
                throw new InvalidArgumentException(sprintf(
                    'Loop detected in step chain: %s',
                    implode(' -> ', $cyclePath),
                ));
            }
        }
    }

    /**
     * @param array<string, string[]> $adjacency
     * @param array<string, string>   $color
     * @param string[]                $cyclePath
     */
    private function hasCycleDfs(string $node, array $adjacency, array &$color, array &$cyclePath): bool
    {
        $color[$node] = 'gray';
        $cyclePath[] = $node;

        foreach ($adjacency[$node] as $neighbor) {
            if ($color[$neighbor] === 'gray') {
                $cyclePath[] = $neighbor;
                $cycleStart = array_search($neighbor, $cyclePath, true);
                $cyclePath = array_slice($cyclePath, (int) $cycleStart);
                return true;
            }

            if ($color[$neighbor] === 'white' && $this->hasCycleDfs($neighbor, $adjacency, $color, $cyclePath)) {
                return true;
            }
        }

        $color[$node] = 'black';
        array_pop($cyclePath);
        return false;
    }

    /**
     * @param array<string, string[]> $adjacency
     */
    private function validateAllStepsConnected(array $adjacency): void
    {
        if ($this->steps === []) {
            return;
        }

        $initSource = null;
        foreach ($this->steps as $step) {
            if (in_array($this->messageInit, $step->getMessages(), true)) {
                $initSource = $step->getSource();
                break;
            }
        }

        if ($initSource === null) {
            return;
        }

        $visited = [];
        $queue = [$initSource];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (array_key_exists($current, $visited)) {
                continue;
            }

            $visited[$current] = true;

            foreach ($adjacency[$current] ?? [] as $neighbor) {
                if (!array_key_exists($neighbor, $visited)) {
                    $queue[] = $neighbor;
                }
            }
        }

        $unreachable = [];
        foreach ($this->steps as $step) {
            if (!array_key_exists($step->getSource(), $visited)) {
                $unreachable[] = $step->getSource();
            }
        }

        if ($unreachable !== []) {
            throw new InvalidArgumentException(sprintf(
                'The following steps are not connected to the flow: %s',
                implode(', ', $unreachable),
            ));
        }
    }

    private function validateNoDanglingReturnTypes(): void
    {
        $allMessages = $this->collectAllMessages();

        foreach ($this->steps as $step) {
            foreach ($step->getReturnTypes() as $returnType) {
                if (is_subclass_of($returnType, MessageEnum::RETURN->interface())) {
                    continue;
                }

                if (!in_array($returnType, $allMessages, true)) {
                    throw new InvalidArgumentException(sprintf(
                        'Step "%s" produces message "%s" that is not consumed by any step.',
                        $step->getSource(),
                        $returnType,
                    ));
                }
            }
        }
    }
}
