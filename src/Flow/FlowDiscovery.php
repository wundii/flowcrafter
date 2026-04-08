<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Flow;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Wundii\Flowcrafter\Attribute\FlowGroup;
use Wundii\Flowcrafter\Interface\FlowInterface;

final class FlowDiscovery
{
    /**
     * @return array<string, string> flowType => group name
     */
    public static function discover(): array
    {
        $classNames = self::resolveClassNames();

        $groups = [];

        foreach ($classNames as $className) {
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

        return $groups;
    }

    /**
     * @return list<class-string>
     */
    private static function resolveClassNames(): array
    {
        $classNames = [];

        $classMapFile = self::resolveVendorComposerPath('autoload_classmap.php');
        if ($classMapFile !== null) {
            /** @var array<class-string, string> $classMap */
            $classMap = require $classMapFile;
            $classNames = array_keys($classMap);
        }

        $psr4ClassNames = self::resolveClassNamesFromPsr4();

        return array_values(array_unique([...$classNames, ...$psr4ClassNames]));
    }

    /**
     * @return list<class-string>
     */
    private static function resolveClassNamesFromPsr4(): array
    {
        $psr4File = self::resolveVendorComposerPath('autoload_psr4.php');
        if ($psr4File === null) {
            return [];
        }

        /** @var array<string, list<string>> $psr4Map */
        $psr4Map = require $psr4File;

        $vendorDir = dirname($psr4File, 2);
        $classNames = [];

        foreach ($psr4Map as $namespace => $directories) {
            foreach ($directories as $directory) {
                if (str_starts_with($directory, $vendorDir)) {
                    continue;
                }

                if (!is_dir($directory)) {
                    continue;
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory),
                );

                /** @var SplFileInfo $file */
                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    if (!self::fileContainsClass($file->getPathname())) {
                        continue;
                    }

                    $relativePath = substr($file->getPathname(), strlen($directory) + 1);
                    $className = $namespace . str_replace(['/', '.php'], ['\\', ''], $relativePath);

                    if (!class_exists($className, false)) {
                        require_once $file->getPathname();
                    }

                    if (!class_exists($className, false)) {
                        continue;
                    }

                    /** @var class-string $className */
                    $classNames[] = $className;
                }
            }
        }

        return $classNames;
    }

    private static function resolveVendorComposerPath(string $filename): ?string
    {
        $candidates = [
            dirname(__DIR__, 2) . '/vendor/composer/' . $filename,
            dirname(__DIR__, 4) . '/composer/' . $filename,
            getcwd() . '/vendor/composer/' . $filename,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function fileContainsClass(string $filePath): bool
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return false;
        }

        $tokens = token_get_all($contents);
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_CLASS) {
                return true;
            }
        }

        return false;
    }
}
