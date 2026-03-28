<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console;

use Composer\InstalledVersions;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Console\Commands\FlowCreateCommand;
use Wundii\Flowcrafter\Console\Commands\FlowDevCommand;
use Wundii\Flowcrafter\Console\Commands\FlowDockerInitCommand;
use Wundii\Flowcrafter\Console\Commands\FlowFrankenPhpObserverCommand;
use Wundii\Flowcrafter\Console\Commands\FlowFrankenPhpServiceCommand;
use Wundii\Flowcrafter\Console\Commands\FlowInitCommand;
use Wundii\Flowcrafter\Console\Commands\FlowMermaidCommand;

final class FlowConsole extends BaseApplication
{
    /**
     * @var string
     */
    public const NAME = 'FlowCrafter';

    public function __construct(
        FlowCreateCommand $flowCreateCommand,
        FlowDevCommand $flowDevCommand,
        FlowDockerInitCommand $flowDockerInitCommand,
        FlowFrankenPhpObserverCommand $flowFrankenPhpObserverCommand,
        FlowFrankenPhpServiceCommand $flowFrankenPhpServiceCommand,
        FlowInitCommand $flowInitCommand,
        FlowMermaidCommand $flowMermaidCommand,
    ) {
        parent::__construct(self::NAME, self::vendorVersion());

        $this->addCommands([
            $flowCreateCommand,
            $flowDevCommand,
            $flowDockerInitCommand,
            $flowFrankenPhpObserverCommand,
            $flowFrankenPhpServiceCommand,
            $flowInitCommand,
            $flowMermaidCommand,
        ]);
        $this->setDefaultCommand('list');
        $this->setDefinition($this->getInputDefinition());
    }

    public static function runExceptionally(Throwable $throwable, ?OutputInterface $output = null): int
    {
        $argv = $_SERVER['argv'] ?? [];
        $argv = array_values((array) $argv);
        $argv = array_map(static fn ($value): string => is_string($value) ? $value : '', $argv);

        $argvInput = new ArgvInput($argv);

        if (!$output instanceof OutputInterface) {
            $output = new ConsoleOutput();
        }

        $symfonyStyle = new SymfonyStyle($argvInput, $output);

        $symfonyStyle->writeln('> ' . implode(' ', $argv));
        $symfonyStyle->writeln('<fg=blue;options=bold>Flow</><fg=yellow;options=bold>Crafter</> ' . self::vendorVersion());
        $symfonyStyle->newLine();

        $symfonyStyle->error($throwable->getMessage());
        $symfonyStyle->writeln($throwable->getTraceAsString());

        return Command::FAILURE;
    }

    public static function vendorVersion(): string
    {
        if (InstalledVersions::isInstalled('wundii/flowcrafter')) {
            $version = InstalledVersions::getPrettyVersion('wundii/flowcrafter');
            if ($version !== null) {
                return $version;
            }
        }

        return 'dev';
    }

    private function getInputDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),
            ...OptionEnum::getInputDefinition($this->getDefaultConfigPath()),
        ]);
    }

    private function getDefaultConfigPath(): string
    {
        return getcwd() . DIRECTORY_SEPARATOR . BootstrapConfig::DEFAULT_CONFIG_FILE;
    }
}
