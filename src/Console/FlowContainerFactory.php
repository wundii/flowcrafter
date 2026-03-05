<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfigInitializer;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfigRequirer;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfigResolver;
use Wundii\Flowcrafter\Bootstrap\BootstrapInputResolver;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;

final class FlowContainerFactory
{
    /**
     * @throws Exception
     */
    public function createFromArgvInput(ArgvInput $argvInput): ContainerBuilder
    {
        $bootstrapInputResolver = new BootstrapInputResolver($argvInput);
        $bootstrapConfigResolver = new BootstrapConfigResolver($bootstrapInputResolver);
        $bootstrapConfig = $bootstrapConfigResolver->getBootstrapConfig();
        $bootstrapConfigRequirer = new BootstrapConfigRequirer($bootstrapConfig);

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->register(BootstrapConfig::class, BootstrapConfig::class)
            ->setPublic(true)
            ->setArgument('$bootstrapConfigFile', $bootstrapConfig->getBootstrapConfigFile());
        $containerBuilder->autowire(BootstrapConfigInitializer::class, BootstrapConfigInitializer::class)
            ->setPublic(true);
        $containerBuilder->autowire(BootstrapConfigResolver::class, BootstrapConfigResolver::class)
            ->setPublic(true);
        $containerBuilder->autowire(BootstrapInputResolver::class, BootstrapInputResolver::class)
            ->setPublic(true);
        $containerBuilder->autowire(FlowcrafterConfig::class, FlowcrafterConfig::class)
            ->setPublic(true);

        $phpFileLoader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__));
        $phpFileLoader->load(__DIR__ . '/../../bin/config.php');

        $containerBuilder->compile();

        $flowcrafterConfig = $containerBuilder->get(FlowcrafterConfig::class);
        if ($flowcrafterConfig instanceof FlowcrafterConfig) {
            $bootstrapConfigRequirer->loadConfigFile($flowcrafterConfig);
        }

        return $containerBuilder;
    }
}
