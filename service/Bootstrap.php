<?php

declare(strict_types=1);

use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfigRequirer;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Service\Routes;

$autoloadCandidates = [
    dirname(__DIR__) . '/vendor/autoload.php', // flowcrafter repo / FrankenPHP root
    dirname(__DIR__, 4) . '/vendor/autoload.php', // installed as composer package
    getcwd() . '/vendor/autoload.php', // PHP built-in server (dev)
];
$autoloadFile = null;
foreach ($autoloadCandidates as $autoloadCandidate) {
    if (file_exists($autoloadCandidate)) {
        $autoloadFile = $autoloadCandidate;
        break;
    }
}

if ($autoloadFile === null) {
    throw new RuntimeException('vendor/autoload.php not found');
}

require $autoloadFile;

$flowcrafterConfigFile = $_ENV['FLOWCRAFTER_CONFIG'] ?? null;
$bootstrapConfig = new BootstrapConfig(is_string($flowcrafterConfigFile) ? $flowcrafterConfigFile : null);
$bootstrapConfigRequirer = new BootstrapConfigRequirer($bootstrapConfig);
$flowcrafterConfig = $bootstrapConfigRequirer->loadConfigFile(new FlowcrafterConfig());
$storage = $flowcrafterConfig->getStorage();

Routes::service($flowcrafterConfig, $storage);

return $flowcrafterConfig;
