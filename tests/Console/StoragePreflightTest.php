<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfigInitializer;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\Preflight\StoragePreflight;

final class StoragePreflightTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/flowcrafter_preflight_' . uniqid('', true);
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->tempDir);
    }

    public function testReturnsFalseAndRendersMissConfigWhenConfigFileMissing(): void
    {
        $bootstrapConfig = new BootstrapConfig(null);

        $bufferedOutput = new BufferedOutput();
        // Non-interactive input → BootstrapConfigInitializer::createConfig() returns null
        // because the user prompt default ('yes') is overridden and ask() returns null.
        $stringInput = new StringInput('');
        $stringInput->setInteractive(false);

        $flowSymfonyStyle = new FlowSymfonyStyle($stringInput, $bufferedOutput);

        $bootstrapConfigInitializer = new BootstrapConfigInitializer(
            new Filesystem(),
            new SymfonyStyle($stringInput, $bufferedOutput),
        );
        $storagePreflight = new StoragePreflight(
            $bootstrapConfig,
            new FlowcrafterConfig(),
            $bootstrapConfigInitializer,
        );

        $result = $storagePreflight->ensureReady($flowSymfonyStyle);

        $this->assertFalse($result);
        $rendered = $bufferedOutput->fetch();
        $this->assertStringContainsString('Pre-flight check', $rendered);
        $this->assertStringContainsString('[MISS]', $rendered);
        $this->assertStringContainsString('Config file', $rendered);
        $this->assertStringContainsString(BootstrapConfig::DEFAULT_CONFIG_FILE . ' — missing', $rendered);
    }

    public function testReturnsFalseWhenStorageConfigIsNotSet(): void
    {
        $configFile = $this->tempDir . '/flowcrafter.php';
        file_put_contents($configFile, "<?php\nreturn static function (\$flowcrafterConfig) {};\n");
        $bootstrapConfig = new BootstrapConfig($configFile);

        $bufferedOutput = new BufferedOutput();
        $stringInput = new StringInput('');
        $stringInput->setInteractive(false);

        $flowSymfonyStyle = new FlowSymfonyStyle($stringInput, $bufferedOutput);

        $bootstrapConfigInitializer = new BootstrapConfigInitializer(
            new Filesystem(),
            new SymfonyStyle($stringInput, $bufferedOutput),
        );
        $storagePreflight = new StoragePreflight(
            $bootstrapConfig,
            new FlowcrafterConfig(),
            $bootstrapConfigInitializer,
        );

        $result = $storagePreflight->ensureReady($flowSymfonyStyle);

        $this->assertFalse($result);
        $rendered = $bufferedOutput->fetch();
        $this->assertStringContainsString('[ OK ]', $rendered);
        $this->assertStringContainsString('Config file', $rendered);
        $this->assertStringContainsString('Storage not configured', $rendered);
    }
}
