<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class FlowContainerFactory
{
    /**
     * @param class-string[] $autowireClasses
     * @param array<class-string|object> $syntheticServices
     * @param array<class-string|object> $dependencies
     */
    public static function build(
        array $autowireClasses,
        array $syntheticServices = [],
        array $dependencies = [],
    ): ContainerBuilder {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setResourceTracking(false);

        foreach ($autowireClasses as $autowireClass) {
            $containerBuilder->autowire($autowireClass)
                ->setPublic(true)
                ->setShared(false);
        }

        foreach ($syntheticServices as $syntheticService) {
            $className = is_object($syntheticService)
                ? get_class($syntheticService)
                : $syntheticService;

            $definition = new Definition($className);
            $definition->setSynthetic(true);
            $definition->setPublic(true);

            $containerBuilder->setDefinition($className, $definition);
        }

        foreach ($dependencies as $dependency) {
            $className = is_object($dependency)
                ? get_class($dependency)
                : $dependency;

            $definition = new Definition($className);
            $definition->setSynthetic(is_object($dependency));
            $definition->setPublic(true);

            $containerBuilder->setDefinition($className, $definition);
        }

        $containerBuilder->compile();

        foreach ($syntheticServices as $syntheticService) {
            if (is_object($syntheticService)) {
                $containerBuilder->set(get_class($syntheticService), $syntheticService);
            }
        }

        foreach ($dependencies as $dependency) {
            if (is_object($dependency)) {
                $containerBuilder->set(get_class($dependency), $dependency);
            }
        }

        return $containerBuilder;
    }
}
