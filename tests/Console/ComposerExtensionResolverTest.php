<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Console\ComposerExtensionResolver;

final class ComposerExtensionResolverTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/composer_ext_resolver_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testExtractsExtensionsFromComposerJson(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require' => [
                'php' => '>=8.2',
                'ext-redis' => '*',
                'ext-pdo_mysql' => '*',
                'some/package' => '^1.0',
            ],
        ]);

        $composerExtensionResolver = new ComposerExtensionResolver();
        $extensions = $composerExtensionResolver->resolve($this->tmpDir);

        $this->assertSame(['pdo_mysql', 'redis'], $extensions);
    }

    public function testReturnsEmptyArrayWhenNoComposerJson(): void
    {
        $composerExtensionResolver = new ComposerExtensionResolver();
        $extensions = $composerExtensionResolver->resolve($this->tmpDir);

        $this->assertSame([], $extensions);
    }

    public function testReturnsEmptyArrayWhenNoRequireKey(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'name' => 'test/project',
        ]);

        $composerExtensionResolver = new ComposerExtensionResolver();
        $extensions = $composerExtensionResolver->resolve($this->tmpDir);

        $this->assertSame([], $extensions);
    }

    public function testReturnsEmptyArrayWhenNoExtEntries(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require' => [
                'php' => '>=8.2',
                'some/package' => '^1.0',
            ],
        ]);

        $composerExtensionResolver = new ComposerExtensionResolver();
        $extensions = $composerExtensionResolver->resolve($this->tmpDir);

        $this->assertSame([], $extensions);
    }

    public function testIncludesFlowcrafterVendorExtensions(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require' => [
                'ext-curl' => '*',
            ],
        ]);

        $flowcrafterDir = $this->tmpDir . '/vendor/wundii/flowcrafter';
        mkdir($flowcrafterDir, 0777, true);
        $this->writeComposerJson($flowcrafterDir, [
            'require' => [
                'ext-mbstring' => '*',
                'ext-intl' => '*',
            ],
        ]);

        $composerExtensionResolver = new ComposerExtensionResolver();
        $extensions = $composerExtensionResolver->resolve($this->tmpDir);

        $this->assertSame(['curl', 'intl', 'mbstring'], $extensions);
    }

    public function testIgnoresOtherVendorPackages(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require' => [
                'ext-curl' => '*',
            ],
        ]);

        $otherVendorDir = $this->tmpDir . '/vendor/some/package';
        mkdir($otherVendorDir, 0777, true);
        $this->writeComposerJson($otherVendorDir, [
            'require' => [
                'ext-mbstring' => '*',
            ],
        ]);

        $composerExtensionResolver = new ComposerExtensionResolver();
        $extensions = $composerExtensionResolver->resolve($this->tmpDir);

        $this->assertSame(['curl'], $extensions);
    }

    public function testDeduplicatesExtensions(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require' => [
                'ext-redis' => '*',
                'ext-curl' => '*',
            ],
        ]);

        $flowcrafterDir = $this->tmpDir . '/vendor/wundii/flowcrafter';
        mkdir($flowcrafterDir, 0777, true);
        $this->writeComposerJson($flowcrafterDir, [
            'require' => [
                'ext-redis' => '*',
            ],
        ]);

        $composerExtensionResolver = new ComposerExtensionResolver();
        $extensions = $composerExtensionResolver->resolve($this->tmpDir);

        $this->assertSame(['curl', 'redis'], $extensions);
    }

    public function testIncludesPlainPdo(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require' => [
                'ext-pdo' => '*',
                'ext-curl' => '*',
            ],
        ]);

        $composerExtensionResolver = new ComposerExtensionResolver();
        $extensions = $composerExtensionResolver->resolve($this->tmpDir);

        $this->assertSame(['curl', 'pdo'], $extensions);
    }

    public function testIgnoresRequireDevSection(): void
    {
        $this->writeComposerJson($this->tmpDir, [
            'require' => [
                'ext-curl' => '*',
            ],
            'require-dev' => [
                'ext-xdebug' => '*',
            ],
        ]);

        $composerExtensionResolver = new ComposerExtensionResolver();
        $extensions = $composerExtensionResolver->resolve($this->tmpDir);

        $this->assertSame(['curl'], $extensions);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeComposerJson(string $dir, array $data): void
    {
        file_put_contents($dir . '/composer.json', json_encode($data, JSON_PRETTY_PRINT));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
