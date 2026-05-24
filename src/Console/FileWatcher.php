<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FileWatcher
{
    /**
     * @var array<string, int>
     */
    private array $snapshot;

    /**
     * @param string[] $directories
     */
    public function __construct(
        private readonly array $directories,
    ) {
        $this->snapshot = $this->buildSnapshot();
    }

    public function hasChanges(): bool
    {
        return $this->buildSnapshot() !== $this->snapshot;
    }

    public function reset(): void
    {
        $this->snapshot = $this->buildSnapshot();
    }

    /**
     * @return string[]
     */
    public static function resolveProjectDirectories(): array
    {
        $psr4File = self::resolveAutoloadPsr4Path();
        if ($psr4File === null) {
            return [];
        }

        /** @var array<string, list<string>> $psr4Map */
        $psr4Map = require $psr4File;
        $vendorDir = dirname($psr4File, 2);
        $directories = [];

        foreach ($psr4Map as $dirs) {
            foreach ($dirs as $dir) {
                if (str_starts_with($dir, $vendorDir)) {
                    continue;
                }

                if (is_dir($dir)) {
                    $directories[] = $dir;
                }
            }
        }

        return array_values(array_unique($directories));
    }

    /**
     * @return array<string, int>
     */
    private function buildSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $snapshot[$file->getPathname()] = $file->getMTime();
            }
        }

        ksort($snapshot);

        return $snapshot;
    }

    private static function resolveAutoloadPsr4Path(): ?string
    {
        $candidates = [
            dirname(__DIR__, 2) . '/vendor/composer/autoload_psr4.php',
            dirname(__DIR__, 4) . '/composer/autoload_psr4.php',
            getcwd() . '/vendor/composer/autoload_psr4.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
