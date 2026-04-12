<?php

declare(strict_types=1);

use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Storage\Config\EsdbConfig;

return static function (FlowcrafterConfig $flowcrafterConfig): void {
    $flowcrafterConfig->setStorageConfig(new EsdbConfig('http://192.168.1.104:3000', 'secret'));
    $flowcrafterConfig->setServerHost('0.0.0.0');
    $flowcrafterConfig->setServerPort(8000);
    $flowcrafterConfig->setServerWorkers(4);
    $flowcrafterConfig->setServerHttps(false);
    $flowcrafterConfig->setServerSecret('sonne');
    $flowcrafterConfig->setServerDescription('Developer Environment');
    $flowcrafterConfig->setServerStorage(__DIR__ . '/data/database-esdb.sqlite');
    $flowcrafterConfig->setDependenciesInjection();
};
