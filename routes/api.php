<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// The user must not be part of the API routes. Sanctum must handle this.
foreach (File::glob(base_path('routes/api/*')) as $filePath) {
    require $filePath;
}
