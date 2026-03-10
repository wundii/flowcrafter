<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\FlowObserver;

final class FlowObserverCommand extends Command
{
    public function __construct(
        private FlowcrafterConfig $flowcrafterConfig
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('observer');
        $this->setDescription('Start the observer process');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $output->writeln(sprintf(
            '<fg=%s>%s</>',
            OutputColorEnum::BLUE->value,
            'the observer is starting now...',
        ));
        $output->writeln('');

        $pidFile = sys_get_temp_dir() . '/flowcrafter-observer.pid';
        file_put_contents($pidFile, (string) getmypid());

        register_shutdown_function(static function () use ($pidFile): void {
            @unlink($pidFile);
        });

        $storage = $this->flowcrafterConfig->getStorage();
        $flowObserver = new FlowObserver($storage);

        $logger = static function (string $message) use ($output): void {
            $output->writeln($message);
        };

        /** @phpstan-ignore-next-line */
        while (true) {
            try {
                $flowObserver->run(logger: $logger);
            } catch (Throwable $e) {
                echo '[Observer] error: ' . $e->getMessage() . PHP_EOL;
                sleep(2);
            }
        }
    }
}
