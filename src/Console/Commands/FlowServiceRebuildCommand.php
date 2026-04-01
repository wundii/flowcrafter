<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\Flow;

final class FlowServiceRebuildCommand extends Command
{
    public function __construct(
        private FlowcrafterConfig $flowcrafterConfig
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('service:rebuild');
        $this->setDescription('Rebuild the SQLite flow_list from the primary storage');
        $this->addOption('clear', null, InputOption::VALUE_NONE, 'Truncate flow_list before rebuilding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startExecuteTime = microtime(true);

        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $storage = $this->flowcrafterConfig->getStorage();

        $clear = (bool) $input->getOption('clear');

        try {
            if ($clear) {
                $storage->truncateFlowList();
                $output->writeln(sprintf(
                    '<fg=%s>%s</>',
                    OutputColorEnum::DEFAULT->value,
                    'flow_list truncated.',
                ));
            }

            $count = 0;

            foreach ($storage->findAllFlowHashes() as $flowHash) {
                $flow = $storage->findFlowByHash($flowHash);
                if (!$flow instanceof Flow) {
                    continue;
                }

                $storage->saveFlow($flow);
                ++$count;
            }

            $output->writeln(sprintf(
                '<fg=%s>Rebuilt %d flow(s).</>',
                OutputColorEnum::DEFAULT->value,
                $count,
            ));
        } catch (Exception $exception) {
            $output->writeln(sprintf(
                '<fg=%s>%s</>',
                OutputColorEnum::RED->value,
                $exception->getMessage(),
            ));
        }

        $output->writeln('');

        $usageExecuteTime = Helper::formatTime(microtime(true) - $startExecuteTime);

        return (int) $output->finishApplication($usageExecuteTime);
    }
}
