<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Models\PurchaseOrders\PurchaseOrder;
use App\Models\Suppliers\Supplier;
use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Customers\Customer;
use App\Models\Products\Product;
use App\Models\Orders\Order;

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
    Route::get('/products', function () {
        return view('products', [
            'products' => Product::with('warengruppe')->orderBy('pArtikelNr')->get(),
            'groups'   => WarehouseGroup::orderBy('pWgNr')->get(),
        ]);
    })->name('products');

    Route::get('/orders', function () {
        return view('orders', [
            'orders' => Order::with(['customer', 'items'])->orderByDesc('aufDat')->get(),
        ]);
    })->name('orders');

    Route::get('/customers', function () {
        return view('customers', [
            'customers' => Customer::orderBy('pKdNr')->get(),
        ]);
    })->name('customers');
    Route::get('/warehouse', function () {
        return view('warehouse', [
            'groups' => WarehouseGroup::orderBy('pWgNr')->get(),
        ]);
    })->name('warehouse');

    Route::get('/purchase-orders', function () {
        return view('purchase-orders', [
            'purchaseOrders' => PurchaseOrder::with('supplier', 'items')->orderByDesc('bestDat')->get(),
        ]);
    })->name('purchase-orders');

    Route::get('/suppliers', function () {
        return view('suppliers', [
            'suppliers' => Supplier::orderBy('pLiefNr')->get(),
        ]);
    })->name('suppliers');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
