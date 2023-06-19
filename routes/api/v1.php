<?php

use App\Helpers\SigningHelper;
use App\Http\Controllers\v1\ImageController;
use App\Http\Controllers\v1\UploadSlotController;
use App\Http\Controllers\v1\VersionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Register API v1 routes here. The file has to be required by the default
| api.php file.
|
*/

Route::prefix('v1')->name('v1.')->group(function () {
    Route::middleware('auth:sanctum')->group(
        function () {
            Route::get('/media/{identifier}/versions', [VersionController::class, 'getVersions']);
            Route::delete('/media/{identifier}', [VersionController::class, 'delete']);
            Route::patch('/media/{identifier}/version/{versionNumber}/set', [VersionController::class, 'setVersion']);

            // Image
            Route::get('/image/{identifier}/version/{versionNumber}', [ImageController::class, 'getVersion']);
            Route::get('/image/derivative/{media}/version/{version}/{transformations?}', [ImageController::class, 'getDerivativeForVersion']);
            Route::post('/image/reserveUploadSlot', [UploadSlotController::class, 'reserveImageUploadSlot']);

            // Video
            Route::post('/video/reserveUploadSlot', [UploadSlotController::class, 'reserveVideoUploadSlot']);
        }
    );

    Route::post('/upload/{uploadSlot}', [UploadSlotController::class, 'receiveFile']);
    Route::get('publickey', fn(): string => SigningHelper::getPublicKey());
});
