<?php

use App\Http\Middleware\RunSetupOnFirstVisit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'XSS' => \App\Http\Middleware\XSS::class,
        ]);
        $middleware->prependToGroup('web', RunSetupOnFirstVisit::class);
        $middleware->redirectTo(guests: fn () => route('login'), users: '/admin/home');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation']);
    })->create();
