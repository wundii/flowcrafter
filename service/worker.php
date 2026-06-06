<?php

declare(strict_types=1);

use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flower\ErrorHandler;
use Wundii\Flower\Flower;

try {
    /** @var FlowcrafterConfig $bootstrap */
    $bootstrap = require __DIR__ . '/bootstrap.php';
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Bootstrap failed: ' . $throwable->getMessage() . "\n");
    exit(1);
}

if (!function_exists('frankenphp_handle_request')) {
    throw new RuntimeException('worker.php requires FrankenPHP — use index.php for the PHP built-in server');
}

$secret = $bootstrap->getServerSecret();

$running = true;

while ($running) {
    $running = frankenphp_handle_request(static function () use ($secret): void {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            return;
        }

        Flower::resetRequest();
        Flower::run($secret, ErrorHandler::create());
    });
}
