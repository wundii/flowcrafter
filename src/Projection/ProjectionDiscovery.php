<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Projection;

use InvalidArgumentException;
use ReflectionClass;
use Wundii\Flowcrafter\Attribute\FlowProjection;
use Wundii\Flowcrafter\ClassResolver;
use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;

final class ProjectionDiscovery
{
    /**
     * @var ProjectionHandlerMeta[]|null
     */
    private static ?array $cache = null;

    /**
     * @param class-string<ProjectionHandlerInterface>[]|null $handlerClasses null = auto-discover from classmap
     * @return ProjectionHandlerMeta[]
     */
    public static function discover(?array $handlerClasses = null): array
    {
        if ($handlerClasses !== null) {
            return self::build($handlerClasses);
        }

        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = self::build(self::resolveHandlerClasses());

        return self::$cache;
    }

    /**
     * @return class-string<ProjectionHandlerInterface>[]
     */
    private static function resolveHandlerClasses(): array
    {
        $handlerClasses = [];

        foreach (ClassResolver::resolve() as $className) {
            if (!is_a($className, ProjectionHandlerInterface::class, true)) {
                continue;
            }

            $reflectionClass = new ReflectionClass($className);
            $attributes = $reflectionClass->getAttributes(FlowProjection::class);

            if ($attributes === []) {
                continue;
            }

            /** @var class-string<ProjectionHandlerInterface> $className */
            $handlerClasses[] = $className;
        }

        return $handlerClasses;
    }

    /**
     * @param class-string<ProjectionHandlerInterface>[] $handlerClasses
     * @return ProjectionHandlerMeta[]
     */
    private static function build(array $handlerClasses): array
    {
        $metas = [];
        $flowTypeToHandler = [];

        foreach ($handlerClasses as $handlerClass) {
            $reflectionClass = new ReflectionClass($handlerClass);

            if (!$reflectionClass->implementsInterface(ProjectionHandlerInterface::class)) {
                throw new InvalidArgumentException(sprintf(
                    'Class "%s" does not implement ProjectionHandlerInterface.',
                    $handlerClass,
                ));
            }

            $attributes = $reflectionClass->getAttributes(FlowProjection::class);
            if ($attributes === []) {
                throw new InvalidArgumentException(sprintf(
                    'Class "%s" is missing the #[FlowProjection] attribute.',
                    $handlerClass,
                ));
            }

            $attribute = $attributes[0]->newInstance();
            $flowTypes = $attribute->flowTypes;

            if ($flowTypes === []) {
                throw new InvalidArgumentException(sprintf(
                    'ProjectionHandler "%s" must define at least one flow type in #[FlowProjection].',
                    $handlerClass,
                ));
            }

            foreach ($flowTypes as $flowType) {
                if (array_key_exists($flowType, $flowTypeToHandler)) {
                    throw new InvalidArgumentException(sprintf(
                        'Flow type "%s" is already registered by "%s", cannot register "%s".',
                        $flowType,
                        $flowTypeToHandler[$flowType],
                        $handlerClass,
                    ));
                }

                $flowTypeToHandler[$flowType] = $handlerClass;
            }

            $metas[] = new ProjectionHandlerMeta(
                handlerClass: $handlerClass,
                flowTypes: $flowTypes,
            );
        }

        return $metas;
    }
}
