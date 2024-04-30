<?php

use App\Enums\MediaStorage;
use App\Enums\MediaType;
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
            Route::get('/media/{media}/versions', [VersionController::class, 'getVersions'])->name('getVersions');
            Route::delete('/media/{media}', [VersionController::class, 'delete'])->name('delete');
            Route::patch('/media/{media}/version/{version}', [VersionController::class, 'setVersion'])->name('setVersion');

            // Image
            Route::get(sprintf('/%s/{media}/version/{version}/original', MediaType::IMAGE->value), [ImageController::class, 'getOriginal'])->name('getOriginal');
            Route::get(sprintf('/%s/{media}/version/{version}/derivative/{transformations?}', MediaType::IMAGE->value), [ImageController::class, 'getDerivativeForVersion'])->name('getDerivativeForVersion');
            Route::post(sprintf('/%s/reserveUploadSlot', MediaType::IMAGE->value), [UploadSlotController::class, 'reserveImageUploadSlot'])->name('reserveImageUploadSlot');

            // Video
            Route::post(sprintf('/%s/reserveUploadSlot', MediaType::VIDEO->value), [UploadSlotController::class, 'reserveVideoUploadSlot'])->name('reserveVideoUploadSlot');
        }
    );

    Route::post('/upload/{uploadSlot}', [UploadSlotController::class, 'receiveFile'])->name('upload');
    Route::get('publickey', fn(): string => SodiumHelper::getPublicKey())->name('getPublicKey');
    Route::get('cacheInvalidationRevision', fn(): string => MediaStorage::ORIGINALS->getDisk()->get(config('transmorpher.cache_invalidation_file_path')) ?? 0)->name('getCacheInvalidationRevision');
});
