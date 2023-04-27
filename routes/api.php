<?php

use App\Helpers\SigningHelper;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\UploadTokenController;
use App\Http\Controllers\VersionController;
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
        Route::get('/media/{identifier}/versions', [VersionController::class, 'getVersions']);
        Route::delete('/media/{identifier}', [VersionController::class, 'delete']);
        Route::patch('/media/{identifier}/version/{versionNumber}/set', [VersionController::class, 'setVersion']);

        // Image
        Route::get('/image/{identifier}/version/{versionNumber}', [ImageController::class, 'getVersion']);
        Route::post('/image/token', [UploadTokenController::class, 'getImageToken']);

        // Video
        Route::post('/video/token', [UploadTokenController::class, 'getVideoToken']);
    }
);

Route::post('/image/upload', [ImageController::class, 'receiveFile']);
Route::post('/video/upload', [VideoController::class, 'receiveFile']);
Route::get('publickey', fn(): string => SigningHelper::getPublicKey());
