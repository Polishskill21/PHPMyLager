<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\ProductController;

Route::get('/status', [SystemController::class, 'status']);

//auth:sanctum could be used later
Route::middleware('auth')->group(function () {

    // All roles can read
    Route::get('products',      [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

    // Admin + writer can create and update
    Route::middleware('role:admin,writer')->group(function () {
        Route::post('products',           [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}',  [ProductController::class, 'update'])->name('products.update');
        Route::patch('products/{product}',[ProductController::class, 'update']);
    });

    // Admin only can delete
    Route::middleware('role:admin')->group(function () {
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });
});