<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Testing;

use PHPUnit\Framework\Assert;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

trait FlowAssertTrait
{
    private ?Flow $flowUnderTest = null;

    private bool|MessageReturnInterface $flowUnderTestResult = false;

    /**
     * Executes a flow without storage and stores the resulting Flow for later assertions.
     *
     * @param class-string<FlowInterface> $flowSource
     * @param array<class-string|object> $dependencies
     * @param class-string[] $includeStubs
     */
    protected function runFlow(
        string $flowType,
        string $flowSource,
        MessageInterface $initMessage,
        ?string $flowSubject = null,
        array $dependencies = [],
        array $includeStubs = [],
    ): bool|MessageReturnInterface {
        $flowRunner = new FlowRunner(
            type: $flowType,
            flowSource: $flowSource,
            flowSubject: $flowSubject,
            dependenciesInjection: $dependencies,
        );

        try {
            $this->flowUnderTestResult = $flowRunner->run($initMessage, includeStubs: $includeStubs);
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
     * Executes a single stub in isolation with a minimal Symfony DI container —
     * no Flow, no schema, no message lifecycle. Mirrors the autowire logic used
     * internally by FlowRunner::buildContainer() but scoped to one stub.
     *
     * @param class-string<StubInterface> $stubSource
     * @param MessageInterface[] $messages
     * @param array<class-string|object> $dependencies
     */
    protected function runStub(
        string $stubSource,
        array $messages,
        array $dependencies = [],
    ): bool|MessageInterface {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setResourceTracking(false);

        foreach ($messages as $message) {
            $definition = new Definition($message::class);
            $definition->setSynthetic(true);
            $definition->setPublic(true);
            $containerBuilder->setDefinition($message::class, $definition);
        }

        foreach ($dependencies as $dependency) {
            $className = is_object($dependency) ? $dependency::class : $dependency;
            $definition = new Definition($className);
            $definition->setSynthetic(is_object($dependency));
            $definition->setPublic(true);
            $containerBuilder->setDefinition($className, $definition);
        }

        $containerBuilder->autowire($stubSource)
            ->setPublic(true)
            ->setShared(false);

        $containerBuilder->compile();

        foreach ($messages as $message) {
            $containerBuilder->set($message::class, $message);
        }

        foreach ($dependencies as $dependency) {
            if (is_object($dependency)) {
                $containerBuilder->set($dependency::class, $dependency);
            }
        }

        $stubInstance = $containerBuilder->get($stubSource);
        if (!$stubInstance instanceof StubInterface) {
            throw new RuntimeException(sprintf(
                'Resolved service "%s" does not implement StubInterface.',
                $stubSource,
            ));
        }

        return $stubInstance->process();
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
                'Expected flow status %s, got %s. Executed stubs: [%s]. Exceptions: %d.',
                $statusEnum->name,
                $actual->name,
                implode(', ', $this->collectStubs($flow)),
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
                'Expected flow to return an instance of %s, got %s. Executed stubs: [%s].',
                $messageClass,
                is_object($result) ? $result::class : gettype($result),
                implode(', ', $this->collectStubs($flow)),
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
                    'Expected all FlowResults to be %s, but stub "%s" returned %s.',
                    $expected ? 'true' : 'false',
                    $result->getStubSource(),
                    $result->getResult() ? 'true' : 'false',
                ),
            );
        }
    }

    /**
     * @param class-string<StubInterface> $stubSource
     */
    protected function assertStubExecuted(string $stubSource, ?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        Assert::assertContains(
            $stubSource,
            $this->collectStubs($flow),
            sprintf(
                'Expected stub "%s" to have been executed. Executed stubs: [%s].',
                $stubSource,
                implode(', ', $this->collectStubs($flow)),
            ),
        );
    }

    /**
     * @param class-string<StubInterface> $stubSource
     */
    protected function assertStubNotExecuted(string $stubSource, ?Flow $flow = null): void
    {
        $flow ??= $this->lastFlow();
        Assert::assertNotContains(
            $stubSource,
            $this->collectStubs($flow),
            sprintf('Expected stub "%s" NOT to have been executed, but it was.', $stubSource),
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
            static fn ($exception): string => sprintf('%s: %s', $exception->getStubSource(), $exception->getMessage()),
            $exceptions,
        );
        Assert::assertCount(
            0,
            $exceptions,
            sprintf('Expected no flow exceptions, got %d: [%s].', count($exceptions), implode(' | ', $messages)),
        );
    }

    /**
     * @param class-string<StubInterface> $stubSource
     */
    protected function assertFlowExceptionFrom(
        string $stubSource,
        ?string $messageContains = null,
        ?Flow $flow = null,
    ): void {
        $flow ??= $this->lastFlow();
        $matched = false;
        foreach ($flow->getFlowExceptions() as $flowException) {
            if ($flowException->getStubSource() !== $stubSource) {
                continue;
            }

            if ($messageContains !== null && !str_contains($flowException->getMessage(), $messageContains)) {
                continue;
            }

            $matched = true;
            break;
        }

        $actual = array_map(
            static fn ($exception): string => sprintf('%s: %s', $exception->getStubSource(), $exception->getMessage()),
            $flow->getFlowExceptions(),
        );
        Assert::assertTrue(
            $matched,
            sprintf(
                'Expected a FlowException from "%s"%s. Recorded exceptions: [%s].',
                $stubSource,
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
    private function collectStubs(Flow $flow): array
    {
        $stubs = [];
        foreach ($flow->getFlowMessages() as $flowMessage) {
            $stubs[$flowMessage->getStubSource()] = true;
        }

        return array_keys($stubs);
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
