<?php

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Helpers\SodiumHelper;
use App\Http\Controllers\V2\DocumentController;
use App\Http\Controllers\V2\ImageController;
use App\Http\Controllers\V2\UploadController;
use App\Http\Controllers\V2\VersionController;
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

        // Uploading
        Route::post('/{mediaType}/reserveUploadSlot', [UploadController::class, 'reserveUploadSlot'])->name('reserveUploadSlot');
        Route::get('/upload/{uploadSlot}/chunkUrl/{chunkNumber}', [UploadController::class, 'getChunkUploadUrl'])->name('getChunkUploadUrl');
        Route::post('/upload/{uploadSlot}/complete', [UploadController::class, 'completeUpload'])->name('completeUpload');
        Route::delete('/upload/{uploadSlot}', [UploadController::class, 'abortUpload'])->name('abortUpload');
    });

    Route::put('/upload/{uploadSlot}', [UploadController::class, 'receiveFile'])->name('upload');
    Route::get('publickey', fn(): string => SodiumHelper::getPublicKey())->name('getPublicKey');
    Route::get('cacheInvalidator', fn(): string => MediaStorage::ORIGINALS->getDisk()->get(config('transmorpher.cache_invalidation_counter_file_path')) ?? 0)->name('getCacheInvalidator');
    Route::get('uploadHandler', fn(): string => config('transmorpher.upload_handler'))->name('getUploadHandler');
});



