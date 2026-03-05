<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
        $this->setDescription('Start the Flowcrafter observer process');
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

        $storage = $this->flowcrafterConfig->getStorage();
        $flowObserver = new FlowObserver($storage);
        $flowObserver->run();

        return self::SUCCESS;
    }
}
