<?php

use App\Enums\MediaType;
use App\Http\Controllers\V1\ImageController;
use App\Http\Controllers\V1\DocumentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Delivery Routes
|--------------------------------------------------------------------------
|
| Public routes that are not part of the api and don't need the middlewares of web routes.
|
*/

// Image
Route::get(sprintf('%s/{user}/{media}/{transformations?}', MediaType::IMAGE->prefix()), [ImageController::class, 'get'])->name('getImageDerivative');

// Document
Route::get(sprintf('%s/{user}/{media}/{transformations?}', MediaType::DOCUMENT->prefix()), [DocumentController::class, 'get'])->name('getDocumentDerivative');
