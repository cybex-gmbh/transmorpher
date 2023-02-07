<?php

use App\Helpers\SigningHelper;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->group(
    function () {
        // Image
        Route::post('/image/upload', [ImageController::class, 'put']);

        // Video
        Route::post('/video/upload', [VideoController::class, 'put']);
    }
);

Route::get('publickey', fn(): string => SigningHelper::getPublicKey());
