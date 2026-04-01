<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use ReflectionObject;
use Wundii\Flowcrafter\Interface\MessageInterface;

abstract readonly class AbstractMessage implements MessageInterface
{
    /**
     * @return array<string, list<string>>
     */
    public function propertyNames(): array
    {
        $result = [];
        $reflectionObject = new ReflectionObject($this);

        $names = [];
        foreach ($reflectionObject->getProperties() as $reflectionProperty) {
            if (!$reflectionProperty->isPromoted()) {
                continue;
            }

            $names[] = $reflectionProperty->getName();
            $value = $reflectionProperty->getValue($this);
            if ($value instanceof self) {
                $result = array_merge($result, $value->propertyNames());
            }
        }

        sort($names);
        $result[$reflectionObject->getShortName()] = $names;

        ksort($result);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [];
        $reflectionObject = new ReflectionObject($this);
        foreach ($reflectionObject->getProperties() as $reflectionProperty) {
            if (!$reflectionProperty->isPromoted()) {
                continue;
            }

            $data[$reflectionProperty->getName()] = $reflectionProperty->getValue($this);
        }

        return $data;
    }
}
