<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use ReflectionClass;
use Wundii\Flowcrafter\Attribute\FlowGroup;
use Wundii\Flowcrafter\Interface\FlowInterface;

final class FlowDiscovery
{
    /**
     * @var array<string, string>|null
     */
    private static ?array $cache = null;

    /**
     * @return array<string, string> flowType => group name
     */
    public static function discover(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $groups = [];

        foreach (ClassResolver::resolve() as $className) {
            if (!is_a($className, FlowInterface::class, true)) {
                continue;
            }

            $reflectionClass = new ReflectionClass($className);
            $attributes = $reflectionClass->getAttributes(FlowGroup::class);

            if ($attributes === []) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $flowType = $className::schema()->type();
            $groups[$flowType] = $attribute->name;
        }

        self::$cache = $groups;

        return self::$cache;
    }
}
