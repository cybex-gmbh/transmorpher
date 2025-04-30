<?php

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Helpers\SodiumHelper;
use App\Http\Controllers\V1\ImageController;
use App\Http\Controllers\V1\DocumentController;
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
            Route::get(sprintf('/%s/{media}/version/{version}/original', MediaType::IMAGE->value), [ImageController::class, 'getOriginal'])->name('getImageOriginal');
            Route::get(sprintf('/%s/{media}/version/{version}/derivative/{transformations?}', MediaType::IMAGE->value), [ImageController::class, 'getDerivativeForVersion'])->name('getImageDerivativeForVersion');

            // Document
            Route::get(sprintf('/%s/{media}/version/{version}/original', MediaType::DOCUMENT->value), [DocumentController::class, 'getOriginal'])->name('getDocumentOriginal');
            Route::get(sprintf('/%s/{media}/version/{version}/derivative/{transformations?}', MediaType::DOCUMENT->value), [DocumentController::class, 'getDerivativeForVersion'])->name('getDocumentDerivativeForVersion');

            // UploadSlot
            Route::post('/{mediaType}/reserveUploadSlot', [UploadSlotController::class, 'reserveUploadSlot'])->name('reserveUploadSlot');
        }
    );

    Route::post('/upload/{uploadSlot}', [UploadSlotController::class, 'receiveFile'])->name('upload');
    Route::get('publickey', fn(): string => SodiumHelper::getPublicKey())->name('getPublicKey');
    Route::get('cacheInvalidator', fn(): string => MediaStorage::ORIGINALS->getDisk()->get(config('transmorpher.cache_invalidation_counter_file_path')) ?? 0)->name('getCacheInvalidator');
});
