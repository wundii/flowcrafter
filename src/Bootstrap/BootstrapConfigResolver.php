<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Bootstrap;

use Exception;
use Wundii\Flowcrafter\Console\OptionEnum;

final class BootstrapConfigResolver
{
    public function __construct(
        private readonly BootstrapInputResolver $bootstrapInputResolver
    ) {
    }

    /**
     * @throws Exception
     */
    public function getBootstrapConfig(): BootstrapConfig
    {
        $configFile = $this->resolveFromInput();

        return new BootstrapConfig($configFile);
    }

    private function resolveFromInput(): ?string
    {
        $configFile = $this->bootstrapInputResolver->getOptionValue(OptionEnum::CONFIG);
        if ($configFile === null) {
            $configFile = getcwd() . DIRECTORY_SEPARATOR . BootstrapConfig::DEFAULT_CONFIG_FILE;
        }

        if (!file_exists($configFile)) {
            return null;
        }

        return $configFile;
    }
}
