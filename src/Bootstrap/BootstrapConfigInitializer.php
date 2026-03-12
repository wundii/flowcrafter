<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Bootstrap;

use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

readonly class BootstrapConfigInitializer
{
    public function __construct(
        private Filesystem $filesystem,
        private SymfonyStyle $symfonyStyle,
    ) {
    }

    public function createConfig(string $projectDirectory): ?string
    {
        $configFile = $projectDirectory . DIRECTORY_SEPARATOR . BootstrapConfig::DEFAULT_CONFIG_FILE;

        if ($this->filesystem->exists($configFile)) {
            $warningMessage = sprintf('The "%s" config already exists.', BootstrapConfig::DEFAULT_CONFIG_FILE);
            $this->symfonyStyle->warning($warningMessage);
            return null;
        }

        $questionMessage = sprintf('No "%s" config found. Should we generate it for you?', BootstrapConfig::DEFAULT_CONFIG_FILE);
        $response = $this->symfonyStyle->ask($questionMessage, 'yes');
        if ($response !== 'yes') {
            return null;
        }

        try {
            $this->filesystem->copy(dirname(__DIR__, 2) . '/templates/flowcrafter.php.dist', $configFile);
        } catch (Exception $exception) {
            $this->symfonyStyle->error($exception->getMessage());
            return null;
        }

        $this->symfonyStyle->success('The config file was generated! You can now run "bin/flowcrafter".');

        return $configFile;
    }
}
