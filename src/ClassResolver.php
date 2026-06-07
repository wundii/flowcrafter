<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class ClassResolver
{
    /**
     * @var array<class-string, string>|null
     */
    private static ?array $map = null;

    /**
     * @return list<class-string>
     */
    public static function resolve(): array
    {
        return array_keys(self::resolveMap());
    }

    /**
     * Map of every resolvable class to its absolute file path.
     *
     * @return array<class-string, string>
     */
    public static function resolveMap(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $map = [];

        $classMapFile = self::resolveVendorComposerPath('autoload_classmap.php');
        if ($classMapFile !== null) {
            /** @var array<class-string, string> $classMap */
            $classMap = require $classMapFile;
            $map = $classMap;
        }

        self::$map = [...$map, ...self::resolveMapFromPsr4()];

        return self::$map;
    }

    /**
     * Instantiable classes whose name starts with the given namespace prefix.
     *
     * @return list<class-string>
     */
    public static function resolveByNamespace(string $namespacePrefix): array
    {
        $prefix = ltrim($namespacePrefix, '\\');

        $classNames = array_filter(
            array_keys(self::resolveMap()),
            static fn (string $class): bool => str_starts_with(ltrim($class, '\\'), $prefix),
        );

        return self::filterInstantiable($classNames);
    }

    /**
     * Instantiable classes whose file is located under the given directory.
     *
     * @return list<class-string>
     */
    public static function resolveByDirectory(string $directory): array
    {
        $base = realpath($directory);
        if ($base === false) {
            return [];
        }

        $classNames = [];
        foreach (self::resolveMap() as $className => $path) {
            $real = realpath($path);
            if ($real !== false && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                $classNames[] = $className;
            }
        }

        return self::filterInstantiable($classNames);
    }

    /**
     * @return array<class-string, string>
     */
    private static function resolveMapFromPsr4(): array
    {
        $psr4File = self::resolveVendorComposerPath('autoload_psr4.php');
        if ($psr4File === null) {
            return [];
        }

        /** @var array<string, list<string>> $psr4Map */
        $psr4Map = require $psr4File;

        $vendorDir = dirname($psr4File, 2);
        $map = [];

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
                    $map[$className] = $file->getPathname();
                }
            }
        }

        return $map;
    }

    /**
     * @param array<int, class-string> $classNames
     * @return list<class-string>
     */
    private static function filterInstantiable(array $classNames): array
    {
        $instantiable = array_filter(
            $classNames,
            static function (string $class): bool {
                if (!class_exists($class)) {
                    return false;
                }

                return (new ReflectionClass($class))->isInstantiable();
            },
        );

        return array_values(array_unique($instantiable));
    }

    private static function resolveVendorComposerPath(string $filename): ?string
    {
        $candidates = [
            dirname(__DIR__) . '/vendor/composer/' . $filename,
            dirname(__DIR__, 3) . '/composer/' . $filename,
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
