<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Orders\Ordercontroller;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Suppliers\SupplierController;
use App\Http\Controllers\PurchaseOrders\PurchaseOrderController;
use App\Http\Controllers\WarehouseGroups\WarehouseGroupController;

// Redirect root to login
Route::get('/', fn() => redirect()->route('login'));

// Guest-only routes (can't visit login if already logged in)
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.post');
});

// Protected routes (must be logged in). Each list page assembles its (cached)
// view payload in its controller's indexView().
Route::middleware('auth')->group(function () {
    Route::get('/dashboard',       fn() => view('dashboard'))->name('dashboard');
    Route::get('/products',        [ProductController::class, 'indexView'])->name('products');
    Route::get('/orders',          [Ordercontroller::class, 'indexView'])->name('orders');
    Route::get('/customers',       [CustomerController::class, 'indexView'])->name('customers');
    Route::get('/warehouse',       [WarehouseGroupController::class, 'indexView'])->name('warehouse');
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'indexView'])->name('purchase-orders');
    Route::get('/suppliers',       [SupplierController::class, 'indexView'])->name('suppliers');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
