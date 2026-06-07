<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Tests\Fixture\Autowire\GreetingConsumer;
use Tests\MockClass\DependencyConstructMock;
use Tests\MockClass\DependencyInterfaceImplMock;
use Tests\MockClass\DependencyInterfaceMock;
use Tests\MockClass\DependencyMock;
use Tests\MockClass\InterfaceBindingStepMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowInterfaceBindingMock;
use Wundii\Flowcrafter\DependencyInjection\DependencyRegistry;
use Wundii\Flowcrafter\FlowContainerFactory;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

final class FlowContainerFactoryTest extends TestCase
{
    public function testBuildWithAutowiredClass(): void
    {
        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [],
            syntheticServices: [],
            dependencyRegistry: (new DependencyRegistry())->autowire(DependencyMock::class),
        );

        $this->assertTrue($containerBuilder->has(DependencyMock::class));
        $this->assertInstanceOf(DependencyMock::class, $containerBuilder->get(DependencyMock::class));
    }

    public function testBuildWithInstance(): void
    {
        $dependencyInterfaceImplMock = new DependencyInterfaceImplMock();

        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [],
            syntheticServices: [],
            dependencyRegistry: (new DependencyRegistry())->instance($dependencyInterfaceImplMock),
        );

        $this->assertTrue($containerBuilder->has(DependencyInterfaceImplMock::class));
        $this->assertSame($dependencyInterfaceImplMock, $containerBuilder->get(DependencyInterfaceImplMock::class));
    }

    public function testBuildWithInterfaceBindingToObject(): void
    {
        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [InterfaceBindingStepMock::class],
            syntheticServices: [new MessageInitMock('hello')],
            dependencyRegistry: (new DependencyRegistry())
                ->bind(DependencyInterfaceMock::class, new DependencyInterfaceImplMock()),
        );

        $step = $containerBuilder->get(InterfaceBindingStepMock::class);
        $this->assertInstanceOf(InterfaceBindingStepMock::class, $step);

        $messageReturn = $step->process();
        $this->assertSame('interface-binding-works:hello', $messageReturn->getData());
    }

    public function testBuildWithInterfaceBindingToClassString(): void
    {
        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [InterfaceBindingStepMock::class],
            syntheticServices: [new MessageInitMock('hello')],
            dependencyRegistry: (new DependencyRegistry())
                ->bind(DependencyInterfaceMock::class, DependencyInterfaceImplMock::class),
        );

        $step = $containerBuilder->get(InterfaceBindingStepMock::class);
        $this->assertInstanceOf(InterfaceBindingStepMock::class, $step);

        $messageReturn = $step->process();
        $this->assertSame('interface-binding-works:hello', $messageReturn->getData());
    }

    public function testBuildWithInterfaceBindingFailsWithoutBinding(): void
    {
        $this->expectException(RuntimeException::class);

        FlowContainerFactory::build(
            autowireClasses: [InterfaceBindingStepMock::class],
            syntheticServices: [new MessageInitMock('hello')],
        );
    }

    public function testBuildWithSameClassAsIdAndConcrete(): void
    {
        $dependencyInterfaceImplMock = new DependencyInterfaceImplMock();

        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [],
            syntheticServices: [],
            dependencyRegistry: (new DependencyRegistry())
                ->bind(DependencyInterfaceImplMock::class, $dependencyInterfaceImplMock),
        );

        $this->assertTrue($containerBuilder->has(DependencyInterfaceImplMock::class));
        $this->assertSame($dependencyInterfaceImplMock, $containerBuilder->get(DependencyInterfaceImplMock::class));
    }

    public function testBuildWithFactory(): void
    {
        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [],
            syntheticServices: [],
            dependencyRegistry: (new DependencyRegistry())->factory(
                DependencyConstructMock::class,
                static fn (ContainerInterface $container): DependencyConstructMock => new DependencyConstructMock('made-by-factory'),
            ),
        );

        $dependency = $containerBuilder->get(DependencyConstructMock::class);
        $this->assertInstanceOf(DependencyConstructMock::class, $dependency);
        $this->assertSame('made-by-factory', $dependency->value);
    }

    public function testFactoryReceivesContainerToResolveOtherServices(): void
    {
        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [],
            syntheticServices: [],
            dependencyRegistry: (new DependencyRegistry())
                ->instance(new DependencyMock())
                ->factory(
                    DependencyConstructMock::class,
                    static function (ContainerInterface $container): DependencyConstructMock {
                        $dependencyMock = $container->get(DependencyMock::class);
                        self::assertInstanceOf(DependencyMock::class, $dependencyMock);

                        return new DependencyConstructMock($dependencyMock->strToUpper('hi'));
                    },
                ),
        );

        $dependency = $containerBuilder->get(DependencyConstructMock::class);
        $this->assertInstanceOf(DependencyConstructMock::class, $dependency);
        $this->assertSame('HI', $dependency->value);
    }

    public function testFactoryWithInterfaceAlias(): void
    {
        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [InterfaceBindingStepMock::class],
            syntheticServices: [new MessageInitMock('hello')],
            dependencyRegistry: (new DependencyRegistry())->factory(
                DependencyInterfaceImplMock::class,
                static fn (ContainerInterface $container): DependencyInterfaceImplMock => new DependencyInterfaceImplMock(),
                alias: DependencyInterfaceMock::class,
            ),
        );

        $step = $containerBuilder->get(InterfaceBindingStepMock::class);
        $this->assertInstanceOf(InterfaceBindingStepMock::class, $step);
        $this->assertSame('interface-binding-works:hello', $step->process()->getData());
    }

    public function testBuildWithAutowireNamespaceInjectsDiscoveredService(): void
    {
        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [GreetingConsumer::class],
            syntheticServices: [],
            dependencyRegistry: (new DependencyRegistry())->autowireNamespace('Tests\\Fixture\\Autowire'),
        );

        $consumer = $containerBuilder->get(GreetingConsumer::class);
        $this->assertInstanceOf(GreetingConsumer::class, $consumer);
        // GreetingService was discovered via the namespace and injected by reference.
        $this->assertSame('hello world', $consumer->message());
    }

    public function testBuildWithAutowireDirectoryInjectsDiscoveredService(): void
    {
        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [GreetingConsumer::class],
            syntheticServices: [],
            dependencyRegistry: (new DependencyRegistry())->autowireDirectory(__DIR__ . '/Fixture/Autowire'),
        );

        $consumer = $containerBuilder->get(GreetingConsumer::class);
        $this->assertInstanceOf(GreetingConsumer::class, $consumer);
        $this->assertSame('hello world', $consumer->message());
    }

    public function testFlowRunnerWithInterfaceBinding(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.interface.binding.v1',
            flowSource: WorkflowInterfaceBindingMock::class,
            dependencyRegistry: (new DependencyRegistry())
                ->bind(DependencyInterfaceMock::class, new DependencyInterfaceImplMock()),
        );

        $result = $flowRunner->run(new MessageInitMock('world'));

        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame('interface-binding-works:world', $result->getData());
    }
}
