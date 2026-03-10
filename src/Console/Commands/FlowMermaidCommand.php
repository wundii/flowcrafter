<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\Converter;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

final class FlowMermaidCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('mermaid');
        $this->setDescription('Generate a Mermaid state diagram for a Flow');
        $this->addArgument(
            'flow',
            InputArgument::REQUIRED,
            'The Flow class (e.g. App\\MyFlow)',
        );
        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'Output directory for the Mermaid diagram',
            './',
        );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        /** @var string $flowClass */
        $flowClass = $input->getArgument('flow');
        /** @var string $outputPath */
        $outputPath = $input->getOption('output');

        if (!class_exists($flowClass)) {
            $output->error(sprintf('Class "%s" not found.', $flowClass));
            return Command::FAILURE;
        }

        if (!is_subclass_of($flowClass, FlowInterface::class)) {
            $output->error(sprintf('Class "%s" does not implement FlowInterface.', $flowClass));
            return Command::FAILURE;
        }

        $flowSchema = FlowSchema::create($flowClass);
        $flow = Flow::create(
            flowType: $flowSchema->type(),
            flowSource: $flowClass,
        );

        $outputPath = rtrim($outputPath, '/') . '/';

        $filename = Converter::flowToDiagram($outputPath, $flow);

        $output->writeln(sprintf(
            '<fg=%s>diagram generated: %s</>',
            OutputColorEnum::GREEN->value,
            $filename,
        ));

        return Command::SUCCESS;
    }
}
