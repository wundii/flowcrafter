<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfigInitializer;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;

final class FlowCreateCommand extends Command
{
    public function __construct(
        private readonly BootstrapConfigInitializer $bootstrapConfigInitializer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('create');
        $this->setDescription('Create a new Flowcrafter configuration file if it does not exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startExecuteTime = microtime(true);

        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $configFile = $this->bootstrapConfigInitializer->createConfig((string) getcwd());
        $outputColor = OutputColorEnum::DEFAULT;
        $outputMessage = 'Configuration file ' . $configFile . ' was successfully created.';

        if ($configFile === null) {
            $output->isFailing();
            $outputColor = OutputColorEnum::RED;
            $outputMessage = 'Configuration file could not be created.';
        }

        $output->writeln(sprintf(
            '<fg=%s>%s</>',
            $outputColor->value,
            $outputMessage,
        ));
        $output->writeln('');

        $usageExecuteTime = Helper::formatTime(microtime(true) - $startExecuteTime);

        return (int) $output->finishApplication($usageExecuteTime);
    }
}
