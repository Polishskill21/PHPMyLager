<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WarehouseGroupController;
use App\Http\Controllers\Ordercontroller;

Route::get('/status', [SystemController::class, 'status']);

//auth:sanctum could be used later
Route::middleware('auth')->group(function () {

    // All roles can read
    Route::get('products',      [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

    Route::get('warehouse-groups', [WarehouseGroupController::class, 'index'])->name('warehouse-groups.index');
    Route::get('warehouse-groups/{id}', [WarehouseGroupController::class, 'show'])->name('warehouse-groups.show');

    Route::get('orders',         [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Admin + writer can create and update
    Route::middleware('role:admin,writer')->group(function () {
        Route::post('products',           [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}',  [ProductController::class, 'update'])->name('products.update');
        // Route::patch('products/{product}',[ProductController::class, 'update']);

        Route::post('warehouse-groups', [WarehouseGroupController::class, 'store'])->name('warehouse-groups.store');
        Route::put('warehouse-groups/{id}', [WarehouseGroupController::class, 'update'])->name('warehouse-groups.update');

        Route::post('orders',         [OrderController::class, 'store'])->name('orders.store');
        Route::put('orders/{order}',  [OrderController::class, 'update'])->name('orders.update');
    });

    // Admin only can delete
    Route::middleware('role:admin')->group(function () {
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        // Route::delete('warehouse-groups/{id}', [WarehouseGroupController::class, 'destroy'])->name('warehouse-groups.destroy');

        Route::delete('orders/{order}',        [OrderController::class, 'destroy'])->name('orders.destroy');
    });
});