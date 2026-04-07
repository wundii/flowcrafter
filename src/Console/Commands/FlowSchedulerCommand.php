<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Heartbeat;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\FlowScheduler;

final class FlowSchedulerCommand extends Command
{
    public function __construct(
        private FlowcrafterConfig $flowcrafterConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('scheduler');
        $this->setDescription('Start the scheduler for time-based flow triggering');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $this->flowcrafterConfig->initializeStorage($output);

        $storage = $this->flowcrafterConfig->getStorage();
        $dependencyInjections = $this->flowcrafterConfig->getDependencyInjections();
        $flowScheduler = new FlowScheduler($storage, $dependencyInjections);

        $scheduleCount = count($flowScheduler->getScheduleAttributes());

        if ($scheduleCount === 0) {
            $output->writeln(sprintf(
                '<fg=%s>no schedules found — add #[FlowSchedule] attribute to ScheduleInterface classes</>',
                OutputColorEnum::YELLOW->value,
            ));
            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<fg=%s>starting scheduler with %d schedule(s)</>',
            OutputColorEnum::BLUE->value,
            $scheduleCount,
        ));
        $output->writeln('');

        $heartbeat = new Heartbeat('scheduler');

        register_shutdown_function(static function () use ($heartbeat): void {
            $heartbeat->cleanup();
        });

        $logger = static function (string $message) use ($output): void {
            $output->writeln($message);
        };

        $heartbeat->touch();

        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            try {
                $flowScheduler->run(logger: $logger, heartbeat: $heartbeat);
            } catch (Throwable $e) {
                $output->writeln('[Scheduler] error: ' . $e->getMessage());
                sleep(2);
            }

            $heartbeat->touch();
        }
    }
}
