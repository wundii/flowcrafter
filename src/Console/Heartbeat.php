<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console;

final class Heartbeat
{
    private readonly string $file;

    public function __construct()
    {
        $this->file = sys_get_temp_dir() . '/flowcrafter/observer.' . gethostname() . '.' . getmypid() . '.heartbeat';
        @mkdir(dirname($this->file), 0755, true);
    }

    public function touch(): void
    {
        @touch($this->file);
    }

    public function cleanup(): void
    {
        @unlink($this->file);
    }
}
