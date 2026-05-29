<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Models\PurchaseOrders\PurchaseOrder;
use App\Models\Suppliers\Supplier;

// Redirect root to login
Route::get('/', fn() => redirect()->route('login'));

// Guest-only routes (can't visit login if already logged in)
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Protected routes (must be logged in)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard',  fn() => view('dashboard'))->name('dashboard');
    Route::get('/products',  fn() => view('products'))->name('products');
    Route::get('/orders',  fn() => view('orders'))->name('orders');
    Route::get('/customers',  fn() => view('customers'))->name('customers');
    Route::get('/warehouse', fn() => view('warehouse'))->name('warehouse');

    Route::get('/purchase-orders', function () {
        return view('purchase-orders', [
            'purchaseOrders' => PurchaseOrder::with('supplier', 'items')->orderByDesc('bestDat')->get(),
        ]);
    })->name('purchase-orders');

    Route::get('/suppliers', function () {
        return view('suppliers', [
            'suppliers' => Supplier::withCount('purchaseOrders')->orderBy('pLiefNr')->get(),
        ]);
    })->name('suppliers');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
