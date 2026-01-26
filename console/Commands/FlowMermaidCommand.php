<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;

final class FlowMermaidCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('mermaid');
        $this->setDescription('Start to lint your PHP files');
        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'Pfad zur Ausgabedatei für das Mermaid-Diagramm'
        );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startExecuteTime = microtime(true);

        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $outputPath = $input->getOption('output');

        if (!is_string($outputPath) || !$outputPath) {
            $output->error('Bitte geben Sie einen gültigen Ausgabepfad mit der Option --output an.');
            return self::FAILURE;
        }

        $output->writeln(sprintf(
            '<fg=%s>mermaid:%s</>',
            OutputColorEnum::BLUE->value,
            $outputPath,
        ));
        $output->writeln('');

        $usageExecuteTime = Helper::formatTime(microtime(true) - $startExecuteTime);

        return (int) $output->finishApplication($usageExecuteTime);
    }
}
