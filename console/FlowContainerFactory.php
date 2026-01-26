<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class FlowContainerFactory
{
    /**
     * @throws Exception
     */
    public function createFromArgvInput(): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();

        $phpFileLoader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__));
        $phpFileLoader->load(__DIR__ . '/../bin/config.php');

        $containerBuilder->compile();

        return $containerBuilder;
    }
}
