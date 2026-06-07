<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Closure;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Wundii\Flowcrafter\DependencyInjection\DependencyRegistry;

final class FlowContainerFactory
{
    /**
     * @param class-string[] $autowireClasses
     * @param array<class-string|object> $syntheticServices
     */
    public static function build(
        array $autowireClasses,
        array $syntheticServices = [],
        ?DependencyRegistry $dependencyRegistry = null,
    ): ContainerBuilder {
        $dependencyRegistry ??= new DependencyRegistry();

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setResourceTracking(false);

        // Framework-internal step/handler/schedule classes: fresh instance per resolution.
        foreach ($autowireClasses as $autowireClass) {
            $containerBuilder->autowire($autowireClass)
                ->setPublic(true)
                ->setShared(false);
        }

        // Framework-internal messages: synthetic, set after compile.
        foreach ($syntheticServices as $syntheticService) {
            $className = is_object($syntheticService)
                ? $syntheticService::class
                : $syntheticService;

            $containerBuilder->setDefinition($className, self::synthetic($className));
        }

        // Explicitly autowired services: public so they can be fetched directly.
        foreach ($dependencyRegistry->getAutowireClasses() as $autowireClass) {
            $containerBuilder->autowire($autowireClass)
                ->setPublic(true);
        }

        // Custom ready-made instances: synthetic, set after compile.
        foreach ($dependencyRegistry->getInstances() as $instance) {
            $containerBuilder->setDefinition($instance::class, self::synthetic($instance::class));
        }

        // Interface bindings: object => synthetic + alias, class-string => autowire + alias.
        foreach ($dependencyRegistry->getBindings() as $id => $concrete) {
            if (is_object($concrete)) {
                $className = $concrete::class;
                $containerBuilder->setDefinition($className, self::synthetic($className));
            } else {
                $className = $concrete;
                $containerBuilder->autowire($className)->setPublic(true);
            }

            if ($id !== $className) {
                $containerBuilder->setAlias($id, $className)->setPublic(true);
            }
        }

        // Lazy factories: the closure is a synthetic service used as the factory of the target id.
        foreach ($dependencyRegistry->getFactories() as $id => [$factory, $alias]) {
            $factoryId = self::factoryId($id);
            $containerBuilder->setDefinition($factoryId, self::synthetic(Closure::class));

            $definition = new Definition($id);
            $definition->setFactory(new Reference($factoryId));
            $definition->setArguments([new Reference('service_container')]);
            $definition->setPublic(true);
            $containerBuilder->setDefinition($id, $definition);

            if ($alias !== null && $alias !== $id) {
                $containerBuilder->setAlias($alias, $id)->setPublic(true);
            }
        }

        // Bulk-discovered services (namespace/directory): private so unused or non-wireable
        // classes are pruned instead of failing compilation; referenced ones are injected.
        foreach ($dependencyRegistry->getAutowireBulkClasses() as $autowireClass) {
            if ($containerBuilder->hasDefinition($autowireClass)) {
                continue;
            }

            $containerBuilder->autowire($autowireClass)
                ->setPublic(false);
        }

        $containerBuilder->compile();

        foreach ($syntheticServices as $syntheticService) {
            if (is_object($syntheticService)) {
                $containerBuilder->set($syntheticService::class, $syntheticService);
            }
        }

        foreach ($dependencyRegistry->getInstances() as $instance) {
            $containerBuilder->set($instance::class, $instance);
        }

        foreach ($dependencyRegistry->getBindings() as $concrete) {
            if (is_object($concrete)) {
                $containerBuilder->set($concrete::class, $concrete);
            }
        }

        foreach ($dependencyRegistry->getFactories() as $id => [$factory]) {
            $containerBuilder->set(self::factoryId($id), $factory);
        }

        return $containerBuilder;
    }

    private static function synthetic(string $className): Definition
    {
        $definition = new Definition($className);
        $definition->setSynthetic(true);
        $definition->setPublic(true);

        return $definition;
    }

    /**
     * @param class-string $id
     */
    private static function factoryId(string $id): string
    {
        return $id . '::factory';
    }
}
