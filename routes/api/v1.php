<?php

use App\Helpers\SodiumHelper;
use App\Http\Controllers\V1\ImageController;
use App\Http\Controllers\V1\UploadSlotController;
use App\Http\Controllers\V1\VersionController;
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
            Route::get('/media/{media}/versions', [VersionController::class, 'getVersions']);
            Route::delete('/media/{media}', [VersionController::class, 'delete']);
            Route::patch('/media/{media}/version/{version}', [VersionController::class, 'setVersion']);

            // Image
            Route::get('/image/{media}/version/{version}/original', [ImageController::class, 'getOriginal']);
            Route::get('/image/{media}/version/{version}/derivative/{transformations?}', [ImageController::class, 'getDerivativeForVersion']);
            Route::post('/image/reserveUploadSlot', [UploadSlotController::class, 'reserveImageUploadSlot'])->name('reserveImageUploadSlot');

            // Video
            Route::post('/video/reserveUploadSlot', [UploadSlotController::class, 'reserveVideoUploadSlot'])->name('reserveVideoUploadSlot');;
        }
    );

    Route::post('/upload/{uploadSlot}', [UploadSlotController::class, 'receiveFile'])->name('upload');
    Route::get('publickey', fn(): string => SodiumHelper::getPublicKey());
});
