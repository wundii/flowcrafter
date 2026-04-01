<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Exception;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;

final class FlowInitCommand extends Command
{
    public function __construct(
        private FlowcrafterConfig $flowcrafterConfig
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('init');
        $this->setDescription('Initialize Flowcrafter Database properties');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startExecuteTime = microtime(true);

        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $storage = $this->flowcrafterConfig->getStorage();
        $storageClass = (new ReflectionClass($storage))->getShortName();

        try {
            $storage->initializeDatabase();
            $output->writeln(sprintf(
                '<fg=%s>Successfully initialized %s database</>',
                OutputColorEnum::DEFAULT->value,
                $storageClass,
            ));
        } catch (Exception $exception) {
            $output->writeln(sprintf(
                '<fg=%s>%s: %s</>',
                OutputColorEnum::RED->value,
                $storageClass,
                $exception->getMessage(),
            ));
        }

        $output->writeln('');

        $usageExecuteTime = Helper::formatTime(microtime(true) - $startExecuteTime);

        return (int) $output->finishApplication($usageExecuteTime);
    }
}
