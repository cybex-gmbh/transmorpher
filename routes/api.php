<?php

use App\Helpers\SigningHelper;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\UploadSlotController;
use App\Http\Controllers\VersionController;
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
        Route::get('/media/{media}/versions', [VersionController::class, 'getVersions']);
        Route::delete('/media/{media}', [VersionController::class, 'delete']);
        Route::patch('/media/{media}/version/{version}/set', [VersionController::class, 'setVersion']);

        // Image
        Route::get('/image/{media}/version/{version}', [ImageController::class, 'getVersion']);
        Route::get('/image/derivative/{media}/version/{version}/{transformations?}', [ImageController::class, 'getDerivativeForVersion']);
        Route::post('/image/reserveUploadSlot', [UploadSlotController::class, 'reserveImageUploadSlot']);

        // Video
        Route::post('/video/reserveUploadSlot', [UploadSlotController::class, 'reserveVideoUploadSlot']);
    }
);

Route::post('/upload/{uploadSlot}', [UploadSlotController::class, 'receiveFile']);
Route::get('publickey', fn(): string => SigningHelper::getPublicKey());
