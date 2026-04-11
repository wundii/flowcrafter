<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console;

use Wundii\Flowcrafter\Console\Commands\FlowDockerInitCommand;

final class ComposerExtensionResolver
{
    /**
     * @return string[]
     */
    public function resolve(string $projectDir): array
    {
        $extensions = [];

        $composerJsonPath = $projectDir . '/composer.json';
        $extensions = array_merge($extensions, $this->extractExtensions($composerJsonPath, true));

        $flowcrafterComposerJson = $projectDir . '/vendor/wundii/flowcrafter/composer.json';
        $extensions = array_merge($extensions, $this->extractExtensions($flowcrafterComposerJson));

        $extensions = array_unique($extensions);
        sort($extensions);

        return $extensions;
    }

    /**
     * @return string[]
     */
    private function extractExtensions(string $composerJsonPath, bool $projectComposer = false): array
    {
        if (!is_file($composerJsonPath)) {
            return [];
        }

        $content = file_get_contents($composerJsonPath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !array_key_exists('require', $data) || !is_array($data['require'])) {
            return [];
        }

        $extensions = [];
        foreach (array_keys($data['require']) as $package) {
            if (str_starts_with($package, 'ext-')) {
                $extensions[] = substr($package, 4);
            }
        }

        if ($projectComposer && array_key_exists('name', $data) && $data['name'] === 'wundii/flowcrafter') {
            $pdoDevDriver = array_filter(
                FlowDockerInitCommand::PDO_DRIVER_CHOICES,
                static fn (?string $v): bool => $v !== null,
            );
            $pdoDevDriver = array_values($pdoDevDriver);

            $extensions = array_merge($extensions, $pdoDevDriver);
        }

        return $extensions;
    }
}
