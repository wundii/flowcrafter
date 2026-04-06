<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console;

final class Heartbeat
{
    private readonly string $file;

    private int $lastTouch = 0;

    public function __construct()
    {
        $this->file = sys_get_temp_dir() . '/flowcrafter/observer.' . gethostname() . '.' . getmypid() . '.heartbeat';
        @mkdir(dirname($this->file), 0755, true);
    }

    public function touch(): void
    {
        @touch($this->file);
        $this->lastTouch = time();
    }

    public function touchIfDue(int $intervalSeconds = 15): void
    {
        if ((time() - $this->lastTouch) >= $intervalSeconds) {
            $this->touch();
        }
    }

    public function cleanup(): void
    {
        @unlink($this->file);
    }
}
