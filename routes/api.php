<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// The user must not be a route parameter. Sanctum must handle this in the middleware.
foreach (File::glob(base_path('routes/api/*')) as $filePath) {
    require $filePath;
}
