<?php

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Helpers\SodiumHelper;
use App\Http\Controllers\V1\ImageController;
use App\Http\Controllers\V1\PdfController;
use App\Http\Controllers\V1\UploadSlotController;
use App\Http\Controllers\V1\VersionController;
use App\Http\Requests\V1\UploadSlotRequest;
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
            Route::post(sprintf('/%s/reserveUploadSlot', MediaType::IMAGE->value), fn(UploadSlotRequest $request) => app(UploadSlotController::class)->reserveUploadSlot($request, MediaType::IMAGE))->name('reserveImageUploadSlot');

            // Pdf
            Route::get(sprintf('/%s/{media}/version/{version}/original', MediaType::PDF->value), [PdfController::class, 'getOriginal'])->name('getPdfOriginal');
            Route::get(sprintf('/%s/{media}/version/{version}/derivative/{transformations?}', MediaType::PDF->value), [PdfController::class, 'getDerivativeForVersion'])->name('getPdfDerivativeForVersion');
            Route::post(sprintf('/%s/reserveUploadSlot', MediaType::PDF->value), fn(UploadSlotRequest $request) => app(UploadSlotController::class)->reserveUploadSlot($request, MediaType::PDF))->name('reservePdfUploadSlot');

            // Video
            Route::post(sprintf('/%s/reserveUploadSlot', MediaType::VIDEO->value), fn(UploadSlotRequest $request) => app(UploadSlotController::class)->reserveUploadSlot($request, MediaType::VIDEO))->name('reserveVideoUploadSlot');
        }
    );

    Route::post('/upload/{uploadSlot}', [UploadSlotController::class, 'receiveFile'])->name('upload');
    Route::get('publickey', fn(): string => SodiumHelper::getPublicKey())->name('getPublicKey');
    Route::get('cacheInvalidator', fn(): string => MediaStorage::ORIGINALS->getDisk()->get(config('transmorpher.cache_invalidation_counter_file_path')) ?? 0)->name('getCacheInvalidator');
});
