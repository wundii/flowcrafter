<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Testing;

use PHPUnit\Framework\Assert;
use RuntimeException;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowContainerFactory;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

trait FlowAssertTrait
{
    private ?Flow $flowUnderTest = null;

    private bool|MessageReturnInterface $flowUnderTestResult = false;

    /**
     * Executes a flow without storage and stores the resulting Flow for later assertions.
     *
     * @param class-string<FlowInterface> $flowSource
     * @param array<class-string|object> $dependencies
     * @param class-string[] $includeSteps
     */
    protected function runFlow(
        string $flowType,
        string $flowSource,
        MessageInterface $initMessage,
        ?string $flowSubject = null,
        array $dependencies = [],
        array $includeSteps = [],
    ): bool|MessageReturnInterface {
        $flowRunner = new FlowRunner(
            type: $flowType,
            flowSource: $flowSource,
            flowSubject: $flowSubject,
            dependenciesInjection: $dependencies,
        );

        try {
            $this->flowUnderTestResult = $flowRunner->run($initMessage, includeSteps: $includeSteps);
        } finally {
            $flow = $flowRunner->getFlow();
            if ($flow instanceof Flow) {
                $this->flowUnderTest = $flow;
            }
        }

        return $this->flowUnderTestResult;
    }

    /**
     * Returns the Flow produced by the most recent runFlow() call.
     */
    protected function lastFlow(): Flow
    {
        if (!$this->flowUnderTest instanceof Flow) {
            throw new RuntimeException('No flow has been run yet. Call runFlow() first.');
        }

        return $this->flowUnderTest;
    }

    /**
     * Returns the result from the most recent runFlow() call.
     */
    protected function lastResult(): bool|MessageReturnInterface
    {
        if (!$this->flowUnderTest instanceof Flow) {
            throw new RuntimeException('No flow has been run yet. Call runFlow() first.');
        }

        return $this->flowUnderTestResult;
    }

    /**
     * Executes a single step in isolation with a minimal Symfony DI container —
     * no Flow, no schema, no message lifecycle. Mirrors the autowire logic used
     * internally by FlowRunner::buildContainer() but scoped to one step.
     *
     * @param class-string<StepInterface> $stepSource
     * @param MessageInterface[] $messages
     * @param array<int|class-string, class-string|object> $dependencies
     */
    protected function runStep(
        string $stepSource,
        array $messages,
        array $dependencies = [],
    ): bool|MessageInterface {
        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [$stepSource],
            syntheticServices: $messages,
            dependencies: $dependencies,
        );

        $stepInstance = $containerBuilder->get($stepSource);
        if (!$stepInstance instanceof StepInterface) {
            throw new RuntimeException(sprintf(
                'Resolved service "%s" does not implement StepInterface.',
                $stepSource,
            ));
        }

        return $stepInstance->process();
    }

    protected function assertFlowOk(?Flow $flow = null): void
    {
        $this->assertFlowStatus(StatusEnum::OK, $flow);
    }

    protected function assertFlowFailed(?Flow $flow = null): void
    {
        $this->assertFlowStatus(StatusEnum::FAILED, $flow);
    }

    protected function assertFlowStatus(StatusEnum $statusEnum, ?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        $actual = $flow->status();
        Assert::assertSame(
            $statusEnum,
            $actual,
            sprintf(
                'Expected flow status %s, got %s. Executed steps: [%s]. Exceptions: %d.',
                $statusEnum->name,
                $actual->name,
                implode(', ', $this->collectSteps($flow)),
                count($flow->getFlowExceptions()),
            ),
        );
    }

    /**
     * @param class-string<MessageReturnInterface> $messageClass
     */
    protected function assertFlowReturned(string $messageClass, ?Flow $flow = null): MessageReturnInterface
    {
        $flow ??= $this->lastFlow();
        $result = $flow === $this->flowUnderTest ? $this->flowUnderTestResult : false;

        Assert::assertInstanceOf(
            $messageClass,
            $result,
            sprintf(
                'Expected flow to return an instance of %s, got %s. Executed steps: [%s].',
                $messageClass,
                is_object($result) ? $result::class : gettype($result),
                implode(', ', $this->collectSteps($flow)),
            ),
        );

        return $result;
    }

    protected function assertFlowBoolResult(bool $expected, ?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        $results = $flow->getFlowResults();
        Assert::assertNotEmpty($results, 'Flow has no bool results recorded.');

        foreach ($results as $result) {
            Assert::assertSame(
                $expected,
                $result->getResult(),
                sprintf(
                    'Expected all FlowResults to be %s, but step "%s" returned %s.',
                    $expected ? 'true' : 'false',
                    $result->getStepSource(),
                    $result->getResult() ? 'true' : 'false',
                ),
            );
        }
    }

    /**
     * @param class-string<StepInterface> $stepSource
     */
    protected function assertFlowBoolResultFrom(string $stepSource, bool $expected, ?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        $matched = false;
        foreach ($flow->getFlowResults() as $flowResult) {
            if ($flowResult->getStepSource() !== $stepSource) {
                continue;
            }

            $matched = true;
            Assert::assertSame(
                $expected,
                $flowResult->getResult(),
                sprintf(
                    'Expected FlowResult from step "%s" to be %s, got %s.',
                    $stepSource,
                    $expected ? 'true' : 'false',
                    $flowResult->getResult() ? 'true' : 'false',
                ),
            );
        }

        $recordedSources = [];
        foreach ($flow->getFlowResults() as $flowResult) {
            $recordedSources[$flowResult->getStepSource()] = true;
        }

        Assert::assertTrue(
            $matched,
            sprintf(
                'Expected a FlowResult from step "%s". Recorded result steps: [%s].',
                $stepSource,
                implode(', ', array_keys($recordedSources)),
            ),
        );
    }

    /**
     * @param class-string<StepInterface> $stepSource
     */
    protected function assertStepExecuted(string $stepSource, ?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        Assert::assertContains(
            $stepSource,
            $this->collectSteps($flow),
            sprintf(
                'Expected step "%s" to have been executed. Executed steps: [%s].',
                $stepSource,
                implode(', ', $this->collectSteps($flow)),
            ),
        );
    }

    /**
     * @param class-string<StepInterface> $stepSource
     */
    protected function assertStepNotExecuted(string $stepSource, ?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        Assert::assertNotContains(
            $stepSource,
            $this->collectSteps($flow),
            sprintf('Expected step "%s" NOT to have been executed, but it was.', $stepSource),
        );
    }

    /**
     * @param class-string<MessageInterface> $messageClass
     */
    protected function assertFlowHasMessage(string $messageClass, ?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        Assert::assertTrue(
            $flow->hasMessage($messageClass),
            sprintf(
                'Expected flow to contain a message of type "%s". Message types: [%s].',
                $messageClass,
                implode(', ', $this->collectMessageClasses($flow)),
            ),
        );
    }

    protected function assertFlowMessageCount(int $expected, ?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        Assert::assertCount(
            $expected,
            $flow->getFlowMessages(),
            sprintf(
                'Expected %d flow messages, got %d. Message types: [%s].',
                $expected,
                count($flow->getFlowMessages()),
                implode(', ', $this->collectMessageClasses($flow)),
            ),
        );
    }

    protected function assertFlowResultCount(int $expected, ?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        Assert::assertCount(
            $expected,
            $flow->getFlowResults(),
            sprintf('Expected %d flow results, got %d.', $expected, count($flow->getFlowResults())),
        );
    }

    protected function assertNoFlowExceptions(?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        $exceptions = $flow->getFlowExceptions();
        $messages = array_map(
            static fn ($exception): string => sprintf('%s: %s', $exception->getStepSource(), $exception->getMessage()),
            $exceptions,
        );
        Assert::assertCount(
            0,
            $exceptions,
            sprintf('Expected no flow exceptions, got %d: [%s].', count($exceptions), implode(' | ', $messages)),
        );
    }

    /**
     * @param class-string<StepInterface> $stepSource
     */
    protected function assertFlowExceptionFrom(
        string $stepSource,
        ?string $messageContains = null,
        ?Flow $flow = null,
    ): void {
        $flow ??= $this->lastFlow();
        $matched = false;
        foreach ($flow->getFlowExceptions() as $flowException) {
            if ($flowException->getStepSource() !== $stepSource) {
                continue;
            }

            if ($messageContains !== null && !str_contains($flowException->getMessage(), $messageContains)) {
                continue;
            }

            $matched = true;
            break;
        }

        $actual = array_map(
            static fn ($exception): string => sprintf('%s: %s', $exception->getStepSource(), $exception->getMessage()),
            $flow->getFlowExceptions(),
        );
        Assert::assertTrue(
            $matched,
            sprintf(
                'Expected a FlowException from "%s"%s. Recorded exceptions: [%s].',
                $stepSource,
                $messageContains !== null ? sprintf(' containing "%s"', $messageContains) : '',
                implode(' | ', $actual),
            ),
        );
    }

    protected function assertFlowRunCount(int $expected, ?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        Assert::assertCount(
            $expected,
            $flow->runs(),
            sprintf('Expected %d flow runs, got %d.', $expected, count($flow->runs())),
        );
    }

    /**
     * @return string[]
     */
    private function collectSteps(Flow $flow): array
    {
        $steps = [];
        foreach ($flow->getFlowMessages() as $flowMessage) {
            $steps[$flowMessage->getStepSource()] = true;
        }

        return array_keys($steps);
    }

    /**
     * @return string[]
     */
    private function collectMessageClasses(Flow $flow): array
    {
        $messages = [];
        foreach ($flow->getFlowMessages() as $flowMessage) {
            $messages[$flowMessage->getMessageSource()] = true;
        }

        return array_keys($messages);
    }
}
