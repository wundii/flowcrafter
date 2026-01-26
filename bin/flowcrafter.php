<?php

declare(strict_types=1);

use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\FlowContainerFactory;

@ini_set('memory_limit', '-1');

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
gc_disable();

$autoloadIncluder = new AutoloadIncluder();
$autoloadIncluder->includeCwdVendorAutoloadIfExists();

final class AutoloadIncluder
{
    public function includeCwdVendorAutoloadIfExists(): void
    {
        $cwdVendorAutoload = getcwd() . '/vendor/autoload.php';
        if (! is_file($cwdVendorAutoload)) {
            return;
        }

        if (! file_exists($cwdVendorAutoload)) {
            return;
        }

        require_once $cwdVendorAutoload;
    }
}

$flowContainerFactory = new FlowContainerFactory();
try {
    $container = $flowContainerFactory->createFromArgvInput();
    $application = $container->get(FlowConsole::class);
    exit($application->run());
} catch (Throwable $throwable) {
    FlowConsole::runExceptionally($throwable);
}
