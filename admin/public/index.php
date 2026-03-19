<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

if (isset($_SERVER['FRANKENPHP_WORKER'])) {
    $handler = $app->make(\Illuminate\Contracts\Http\Kernel::class);

    frankenphp_handle_request(function () use ($app, $handler) {
        $request = Request::capture();
        $response = $handler->handle($request);
        $response->send();
        $handler->terminate($request, $response);
    });
} else {
    $app->handleRequest(Request::capture());
}
