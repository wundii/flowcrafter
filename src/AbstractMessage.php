<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use ReflectionObject;
use Wundii\Flowcrafter\Interface\MessageInterface;

abstract readonly class AbstractMessage implements MessageInterface
{
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
