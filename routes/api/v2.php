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
| Register API v2 routes here.
| The file has to be required by the default api.php file.
|
*/

Route::prefix('v2')->name('v2.')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/media/{media}/versions', [VersionController::class, 'getVersions'])->name('versions.get');
        Route::delete('/media/{media}', [VersionController::class, 'delete'])->name('media.delete');
        Route::patch('/media/{media}/versions/{version}', [VersionController::class, 'setVersion'])->name('versions.set');

        // Image
        Route::get(sprintf('/%s/{media}/versions/{version}/original', MediaType::IMAGE->value), [ImageController::class, 'getOriginal'])->name('images.original.get');
        Route::get(sprintf('/%s/{media}/versions/{version}/derivative/{transformations?}', MediaType::IMAGE->value), [ImageController::class, 'getDerivativeForVersion'])->name('images.derivative.get');

        // Document
        Route::get(sprintf('/%s/{media}/versions/{version}/original', MediaType::DOCUMENT->value), [DocumentController::class, 'getOriginal'])->name('documents.original.get');
        Route::get(sprintf('/%s/{media}/versions/{version}/derivative/{transformations?}', MediaType::DOCUMENT->value), [DocumentController::class, 'getDerivativeForVersion'])->name('documents.derivative.get');

        // Uploading
        Route::post('/{mediaType}/upload/reserve', [UploadController::class, 'reserveUploadSlot'])->name('upload.reserve');
        Route::get('/upload/{uploadSlot}/chunkUrl/{chunkNumber}', [UploadController::class, 'getUploadUrl'])->name('upload.url');
        Route::post('/upload/{uploadSlot}/complete', [UploadController::class, 'completeUpload'])->name('upload.complete');
        Route::delete('/upload/{uploadSlot}', [UploadController::class, 'abortUpload'])->name('upload.abort');
    });

    Route::put('/upload/{uploadSlot}', [UploadController::class, 'receiveFile'])->name('upload.receive');

    // Meta information
    Route::get('/meta/publicKey', fn(): string => SodiumHelper::getPublicKey())->name('meta.publickey');
    Route::get('/meta/cacheInvalidator', fn(): string => MediaStorage::ORIGINALS->getDisk()->get(config('transmorpher.cache_invalidation_counter_file_path')) ?? 0)->name('meta.cache.invalidator');
    Route::get('/meta/uploadHandler', fn(): string => config('transmorpher.upload_handler'))->name('meta.upload.handler');
});
