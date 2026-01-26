<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Output;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Style\SymfonyStyle;

final class FlowSymfonyStyle extends SymfonyStyle
{
    private bool $isSuccess = true;

    public function startApplication(string $version): void
    {
        $argv = $_SERVER['argv'] ?? [];
        $argv = array_values((array) $argv);
        $argv = array_map(static fn ($value): string => is_string($value) ? $value : '', $argv);

        $message = sprintf(
            '<fg=blue;options=bold>PHP</><fg=yellow;options=bold>Lint</> %s - current PHP version: %s',
            $version,
            PHP_VERSION,
        );

        $this->writeln('> ' . implode(' ', $argv));
        $this->writeln($message);
        $this->writeln('');
    }

    public function finishApplication(string $executionTime): bool
    {
        $usageMemory = Helper::formatMemory(memory_get_usage(true));

        $this->writeln(sprintf('Memory usage: %s', $usageMemory));

        if (!$this->isSuccess) {
            $this->error(sprintf('Finished in %s', $executionTime));
            return true;
        }

        $this->success(sprintf('Finished in %s', $executionTime));
        return false;
    }
}
