<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Console\FileWatcher;

final class FileWatcherTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/file_watcher_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        file_put_contents($this->tmpDir . '/ExistingClass.php', '<?php class ExistingClass {}');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testHasChangesReturnsFalseWhenUnchanged(): void
    {
        $fileWatcher = new FileWatcher([$this->tmpDir]);

        $this->assertFalse($fileWatcher->hasChanges());
    }

    public function testHasChangesDetectsNewFile(): void
    {
        $fileWatcher = new FileWatcher([$this->tmpDir]);

        file_put_contents($this->tmpDir . '/NewClass.php', '<?php class NewClass {}');

        $this->assertTrue($fileWatcher->hasChanges());
    }

    public function testHasChangesDetectsModifiedFile(): void
    {
        $fileWatcher = new FileWatcher([$this->tmpDir]);

        sleep(1);
        file_put_contents($this->tmpDir . '/ExistingClass.php', '<?php class ExistingClass { public function foo(): void {} }');

        $this->assertTrue($fileWatcher->hasChanges());
    }

    public function testHasChangesDetectsDeletedFile(): void
    {
        $fileWatcher = new FileWatcher([$this->tmpDir]);

        unlink($this->tmpDir . '/ExistingClass.php');

        $this->assertTrue($fileWatcher->hasChanges());
    }

    public function testResetUpdatesSnapshot(): void
    {
        $fileWatcher = new FileWatcher([$this->tmpDir]);

        file_put_contents($this->tmpDir . '/NewClass.php', '<?php class NewClass {}');
        $this->assertTrue($fileWatcher->hasChanges());

        $fileWatcher->reset();
        $this->assertFalse($fileWatcher->hasChanges());
    }

    public function testIgnoresNonPhpFiles(): void
    {
        $fileWatcher = new FileWatcher([$this->tmpDir]);

        file_put_contents($this->tmpDir . '/readme.txt', 'Hello');
        file_put_contents($this->tmpDir . '/config.yaml', 'key: value');

        $this->assertFalse($fileWatcher->hasChanges());
    }

    public function testHandlesNonExistentDirectory(): void
    {
        $fileWatcher = new FileWatcher(['/tmp/non_existent_dir_' . uniqid()]);

        $this->assertFalse($fileWatcher->hasChanges());
    }

    public function testWatchesSubdirectories(): void
    {
        mkdir($this->tmpDir . '/Sub', 0777, true);
        $fileWatcher = new FileWatcher([$this->tmpDir]);

        file_put_contents($this->tmpDir . '/Sub/SubClass.php', '<?php class SubClass {}');

        $this->assertTrue($fileWatcher->hasChanges());
    }

    public function testResolveProjectDirectoriesReturnsNonEmptyArray(): void
    {
        $directories = FileWatcher::resolveProjectDirectories();

        $this->assertNotEmpty($directories);
    }

    public function testResolveProjectDirectoriesExcludesVendor(): void
    {
        $directories = FileWatcher::resolveProjectDirectories();

        foreach ($directories as $directory) {
            $this->assertStringNotContainsString('/vendor/', $directory);
        }
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
            if ($item === '.' || $item === '..') {
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
