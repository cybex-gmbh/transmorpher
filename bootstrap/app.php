<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Using base_path() instead of __DIR__ because it's more uniform.
        web: base_path('routes/web.php'),
        api: base_path('routes/api.php'),
        commands: base_path('routes/console.php'),
        health: '/up',
        then: function () {
            Route::middleware(SubstituteBindings::class)
                ->group(base_path('routes/delivery.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->wantsJson() && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json([
                    'message' =>
                        sprintf('Requested %s couldn\'t be found.', class_basename($e->getPrevious()->getModel())),
                ], 404);
            }
        });
    })->create();
