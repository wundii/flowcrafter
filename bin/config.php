<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $services->defaults()
        ->public()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('Wundii\\Flowcrafter\\Console\\', dirname(__DIR__) . '/src/Console/')
        ->public()
        ->autowire();

    $services->set(ArgvInput::class);
    $services->set(ConsoleOutput::class);
    $services->set(Filesystem::class);
    $services->set(Finder::class);
    $services->set(SymfonyStyle::class);

    $services->alias(InputInterface::class, ArgvInput::class);
    $services->alias(OutputInterface::class, ConsoleOutput::class);
};
