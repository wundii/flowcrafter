<?php

declare(strict_types=1);

namespace Wundii\Service;

use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\FlowPreflight;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flower\Flower;
use Wundii\Flower\MethodEnum;
use Wundii\Service\Controller\DevController;
use Wundii\Service\Controller\ExceptionController;
use Wundii\Service\Controller\FlowController;
use Wundii\Service\Controller\HealthController;
use Wundii\Service\Controller\InfoController;
use Wundii\Service\Controller\QueueController;
use Wundii\Service\Controller\ScheduleController;
use Wundii\Service\Controller\SchemaController;

final class Routes
{
    public static function service(FlowcrafterConfig $flowcrafterConfig, StorageInterface $storage): void
    {
        $router = Flower::router();

        $exceptionController = new ExceptionController($storage);
        $flowPreflight = new FlowPreflight();
        $flowController = new FlowController($flowcrafterConfig, $storage, $flowPreflight);
        $healthController = new HealthController();
        $infoController = new InfoController($flowcrafterConfig, $storage);
        $queueController = new QueueController($storage, $flowPreflight);
        $scheduleController = new ScheduleController($flowcrafterConfig, $storage);
        $schemaController = new SchemaController($storage);

        $router->add('/', MethodEnum::GET, $healthController->index(...));
        $router->add('/metrics', MethodEnum::GET, $infoController->metrics(...));

        $router->add('/api/ping', MethodEnum::GET, $healthController->ping(...));
        $router->add('/api/info', MethodEnum::GET, $infoController->info(...));

        $router->add('/api/flow/flow-list', MethodEnum::GET, $flowController->list(...));
        $router->add('/api/flow/flow-details', MethodEnum::GET, $flowController->detail(...));
        $router->add('/api/flow/flow-run', MethodEnum::POST, $flowController->run(...));
        $router->add('/api/flow/flow-search', MethodEnum::GET, $flowController->search(...));
        $router->add('/api/flow/flow-stats', MethodEnum::GET, $flowController->stats(...));
        $router->add('/api/flow/flow-type-stats', MethodEnum::GET, $flowController->types(...));

        $router->add('/api/flow/schema-list', MethodEnum::GET, $schemaController->list(...));
        $router->add('/api/flow/step-source', MethodEnum::GET, $schemaController->stepSource(...));
        $router->add('/api/flow/step-source-list', MethodEnum::GET, $schemaController->stepSources(...));
        $router->add('/api/flow/message-source-list', MethodEnum::GET, $schemaController->messageSources(...));

        $router->add('/api/schedule/schedule-list', MethodEnum::GET, $scheduleController->list(...));
        $router->add('/api/schedule/flow-run', MethodEnum::POST, $scheduleController->run(...));
        $router->add('/api/schedule/schedule-source', MethodEnum::GET, $scheduleController->source(...));

        $router->add('/api/flow/exceptions-stats', MethodEnum::GET, $exceptionController->stats(...));
        $router->add('/api/flow/exception-list', MethodEnum::GET, $exceptionController->list(...));

        $router->add('/api/queue/queue-list', MethodEnum::GET, $queueController->list(...));
        $router->add('/api/queue/queue-count', MethodEnum::GET, $queueController->count(...));
        $router->add('/api/queue/enqueue', MethodEnum::POST, $queueController->enqueue(...));

        if ($flowcrafterConfig->getServerDev()) {
            $devController = new DevController($flowcrafterConfig, $storage);
            $router->add('/api/dev/flow-list', MethodEnum::GET, $devController->flows(...));
            $router->add('/api/dev/flow-source', MethodEnum::GET, $devController->flow(...));
            $router->add('/api/dev/flow-run', MethodEnum::POST, $devController->run(...));
        }
    }
}
