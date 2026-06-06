<?php

declare(strict_types=1);

use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flower\ErrorHandler;
use Wundii\Flower\Flower;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    /** @var FlowcrafterConfig $bootstrap */
    $bootstrap = require __DIR__ . '/bootstrap.php';
} catch (Throwable $throwable) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $throwable->getMessage(),
    ]);
    exit;
}

Flower::run($bootstrap->getServerSecret(), ErrorHandler::create());
