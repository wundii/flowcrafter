<?php

declare(strict_types=1);

namespace Wundii\Service;

use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flower\Flower;
use Wundii\Flower\MethodEnum;
use Wundii\Service\Controller\ExceptionController;
use Wundii\Service\Controller\FlowController;
use Wundii\Service\Controller\HealthController;
use Wundii\Service\Controller\InfoController;
use Wundii\Service\Controller\QueueController;
use Wundii\Service\Controller\SchemaController;

final class Routes
{
    public static function service(FlowcrafterConfig $flowcrafterConfig, StorageInterface $storage): void
    {
        $router = Flower::router();

        $exceptionController = new ExceptionController($storage);
        $flowController = new FlowController($flowcrafterConfig, $storage);
        $healthController = new HealthController();
        $infoController = new InfoController($flowcrafterConfig, $storage);
        $queueController = new QueueController($storage);
        $schemaController = new SchemaController($storage);

        $router->add('/', MethodEnum::GET, $healthController->index(...));
        $router->add('/metrics', MethodEnum::GET, $infoController->metrics(...));

        $router->add('/api/ping', MethodEnum::GET, $healthController->ping(...));
        $router->add('/api/info', MethodEnum::GET, $infoController->info(...));

        $router->add('/api/flows', MethodEnum::GET, $flowController->list(...));
        $router->add('/api/flows/stats', MethodEnum::GET, $flowController->stats(...));
        $router->add('/api/flows/search', MethodEnum::GET, $flowController->search(...));
        $router->add('/api/flows/detail', MethodEnum::GET, $flowController->detail(...));
        $router->add('/api/flows/run', MethodEnum::POST, $flowController->run(...));

        $router->add('/api/schemas', MethodEnum::GET, $schemaController->list(...));
        $router->add('/api/schema/stub-source', MethodEnum::GET, $schemaController->stubSource(...));
        $router->add('/api/schema/stub-sources', MethodEnum::GET, $schemaController->stubSources(...));

        $router->add('/api/exceptions', MethodEnum::GET, $exceptionController->list(...));

        $router->add('/api/queues', MethodEnum::GET, $queueController->list(...));
        $router->add('/api/queue/count', MethodEnum::GET, $queueController->count(...));
        $router->add('/api/queue', MethodEnum::POST, $queueController->enqueue(...));
    }
}
