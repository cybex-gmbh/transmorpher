<?php

use App\Http\Controllers\V1\ImageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Image
Route::get('/{user}/{media}/{transformations?}', [ImageController::class, 'get'])->name('getDerivative');
