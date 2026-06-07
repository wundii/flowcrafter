<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Heartbeat;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\Projection\ProjectionDiscovery;
use Wundii\Flowcrafter\Projection\ProjectionWorker;

final class FlowProjectionWorkerCommand extends Command
{
    public function __construct(
        private FlowcrafterConfig $flowcrafterConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('projection:worker');
        $this->setDescription('Start the projection worker for async projection handling');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $metas = ProjectionDiscovery::discover();
        if ($metas === []) {
            $output->writeln(sprintf(
                '<fg=%s>No projection handlers found. Add #[FlowProjection] attribute to ProjectionHandlerInterface classes.</>',
                OutputColorEnum::YELLOW->value,
            ));
            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<fg=%s>Starting projection worker with %d handler(s)...</>',
            OutputColorEnum::DEFAULT->value,
            count($metas),
        ));

        $storage = $this->flowcrafterConfig->getStorage();
        $queue = $this->flowcrafterConfig->getQueue();
        $dependencyRegistry = $this->flowcrafterConfig->getDependencyRegistry();

        $heartbeat = new Heartbeat('projection');
        register_shutdown_function(static function () use ($heartbeat): void {
            $heartbeat->cleanup();
        });

        $heartbeat->touch();

        $logger = static function (string $message) use ($output, $heartbeat): void {
            $output->writeln($message);
            $heartbeat->touchIfDue();
        };

        $projectionWorker = new ProjectionWorker($storage, $queue, $metas, $dependencyRegistry);

        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            $projectionWorker->tick($logger);
            $heartbeat->touchIfDue();
            usleep(1_000_000);
        }
    }
}
