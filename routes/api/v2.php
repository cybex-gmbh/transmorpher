<?php

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Helpers\SodiumHelper;
use App\Http\Controllers\V1\DocumentController;
use App\Http\Controllers\V1\ImageController;
use App\Http\Controllers\V1\VersionController;
use App\Http\Controllers\V2\UploadSlotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v2 Routes
|--------------------------------------------------------------------------
|
| Register API v2 routes here. The file has to be required by the default
| api.php file.
|
*/

Route::prefix('v2')->name('v2.')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
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
    });

    // All chunk-phase endpoints are public; the upload token is the secret.
    Route::post('/upload/{uploadSlot}', [UploadSlotController::class, 'receiveFile'])->name('upload');
    Route::get('/upload/{uploadSlot}/chunkUrl/{chunkNumber}', [UploadSlotController::class, 'getChunkUploadUrl'])->name('chunkUrl');
    Route::post('/upload/{uploadSlot}/complete', [UploadSlotController::class, 'completeUpload'])->name('completeUpload');
    Route::delete('/upload/{uploadSlot}', [UploadSlotController::class, 'abortUpload'])->name('abortUpload');

    Route::get('publickey', fn(): string => SodiumHelper::getPublicKey())->name('getPublicKey');
    Route::get('cacheInvalidator', fn(): string => MediaStorage::ORIGINALS->getDisk()->get(config('transmorpher.cache_invalidation_counter_file_path')) ?? 0)->name('getCacheInvalidator');
});



