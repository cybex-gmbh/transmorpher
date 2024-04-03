<?php

use App\Enums\MediaType;
use App\Http\Controllers\V1\ImageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Image
Route::get(sprintf('%s/{user}/{media}/{transformations?}', MediaType::IMAGE->prefix()), [ImageController::class, 'get'])->name('getDerivative');
