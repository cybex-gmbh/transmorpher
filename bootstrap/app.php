<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('delivery')
                ->group(base_path('routes/delivery.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('delivery', [
            SubstituteBindings::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
