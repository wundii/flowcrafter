<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Bootstrap;

use Closure;
use Exception;
use ReflectionFunction;
use ReflectionNamedType;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;

final class BootstrapConfigRequirer
{
    public function __construct(
        private readonly BootstrapConfig $bootstrapConfig
    ) {
    }

    /**
     * @throws Exception
     */
    public function loadConfigFile(FlowcrafterConfig $flowcrafterConfig): FlowcrafterConfig
    {
        $bootstrapConfigFile = $this->bootstrapConfig->getBootstrapConfigFile();
        if ($bootstrapConfigFile === null) {
            return $flowcrafterConfig;
        }

        $fn = require_once $this->bootstrapConfig->getBootstrapConfigFile();
        if (!is_callable($fn)) {
            throw new Exception('BootstrapConfig ' . $this->bootstrapConfig->getBootstrapConfigFile() . ' file is not callable.');
        }

        if (!$fn instanceof Closure) {
            throw new Exception('BootstrapConfig ' . $this->bootstrapConfig->getBootstrapConfigFile() . ' file is not a closure.');
        }

        $reflectionFunction = new ReflectionFunction($fn);
        if ($reflectionFunction->getNumberOfParameters() === 0) {
            throw new Exception('BootstrapConfig ' . $this->bootstrapConfig->getBootstrapConfigFile() . ' file has no parameters.');
        }

        foreach ($reflectionFunction->getParameters() as $reflectionParameter) {
            if (
                $reflectionParameter->hasType()
                && $reflectionParameter->getType() instanceof ReflectionNamedType
                && $reflectionParameter->getType()->getName() === FlowcrafterConfig::class
            ) {
                break;
            }

            // flowcrafterConfig parameter must be on the first position
            throw new Exception('BootstrapConfig ' . $this->bootstrapConfig->getBootstrapConfigFile() . ' file has no flowcrafterConfig parameter.');
        }

        $fn($flowcrafterConfig);

        return $flowcrafterConfig;
    }
}
