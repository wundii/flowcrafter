<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use InvalidArgumentException;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class FlowBuilder
{
    /**
     * @var Stub[]
     */
    private array $stubs = [];

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
     * @param class-string<StubInterface> $stub
     */
    public function addStub(string $stub): void
    {
        Assert::classString(
            $stub,
            StubInterface::class,
            'Stub must be an instance of StubInterface'
        );

        foreach ($this->stubs as $existing) {
            if ($existing->getSource() === $stub) {
                throw new InvalidArgumentException(sprintf(
                    'Stub "%s" is already added to the flow.',
                    $stub,
                ));
            }
        }

        $this->stubs[] = Stub::create($stub);
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
        $this->validateAllStubsConnected($adjacency);
        $this->validateNoDanglingReturnTypes();

        return new FlowSchema($this->type, $this->stubs);
    }

    /**
     * @return class-string[]
     */
    private function collectAllMessages(): array
    {
        $messages = [];
        foreach ($this->stubs as $stub) {
            array_push($messages, ...$stub->getMessages());
        }

        return $messages;
    }

    /**
     * @return class-string[]
     */
    private function collectAllReturnTypes(): array
    {
        $returnTypes = [];
        foreach ($this->stubs as $stub) {
            array_push($returnTypes, ...$stub->getReturnTypes());
        }

        return array_unique($returnTypes);
    }

    /**
     * @return array<string, string[]> map of stub source class → list of successor stub source classes
     */
    private function buildAdjacencyMap(): array
    {
        /** @var array<class-string, string[]> $messageToStubs message class → list of stub sources consuming it */
        $messageToStubs = [];
        foreach ($this->stubs as $stub) {
            foreach ($stub->getMessages() as $messageClass) {
                $messageToStubs[$messageClass][] = $stub->getSource();
            }
        }

        $adjacency = [];
        foreach ($this->stubs as $stub) {
            $source = $stub->getSource();
            $adjacency[$source] = [];

            foreach ($stub->getReturnTypes() as $returnType) {
                foreach ($messageToStubs[$returnType] ?? [] as $consumerSource) {
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
                    'Loop detected in stub chain: %s',
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
    private function validateAllStubsConnected(array $adjacency): void
    {
        if ($this->stubs === []) {
            return;
        }

        $initSource = null;
        foreach ($this->stubs as $stub) {
            if (in_array($this->messageInit, $stub->getMessages(), true)) {
                $initSource = $stub->getSource();
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
        foreach ($this->stubs as $stub) {
            if (!array_key_exists($stub->getSource(), $visited)) {
                $unreachable[] = $stub->getSource();
            }
        }

        if ($unreachable !== []) {
            throw new InvalidArgumentException(sprintf(
                'The following stubs are not connected to the flow: %s',
                implode(', ', $unreachable),
            ));
        }
    }

    private function validateNoDanglingReturnTypes(): void
    {
        $allMessages = $this->collectAllMessages();

        foreach ($this->stubs as $stub) {
            foreach ($stub->getReturnTypes() as $returnType) {
                if (is_subclass_of($returnType, MessageEnum::RETURN->interface())) {
                    continue;
                }

                if (!in_array($returnType, $allMessages, true)) {
                    throw new InvalidArgumentException(sprintf(
                        'Stub "%s" produces message "%s" that is not consumed by any stub.',
                        $stub->getSource(),
                        $returnType,
                    ));
                }
            }
        }
    }
}
