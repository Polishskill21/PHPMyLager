<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Orders\Ordercontroller;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Suppliers\SupplierController;
use App\Http\Controllers\PurchaseOrders\PurchaseOrderController;
use App\Http\Controllers\WarehouseGroups\WarehouseGroupController;
use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Products\Product;
use App\Models\Orders\OrderItem;
use App\Support\DomainCache;

// Redirect root to login
Route::get('/', fn() => redirect()->route('login'));

// Guest-only routes (can't visit login if already logged in)
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.post');
});

// Protected routes (must be logged in)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard',  fn() => view('dashboard'))->name('dashboard');
    Route::get('/products', function (Request $request) {
        $chunk = app(ProductController::class)->firstChunk($request);

        return view('products', [
            'firstRows' => $chunk['rows'],
            'meta'      => $chunk['meta'],
            'groups'    => WarehouseGroup::orderBy('pWgNr')->get(),
            'lowCount'  => DomainCache::remember(
                DomainCache::PRODUCTS,
                'products:low-count',
                fn () => Product::where(Product::COL_BESTAND, '>', 0)
                    ->whereColumn(Product::COL_BESTAND, '<=', Product::COL_MELDE_BEST)
                    ->count()
            ),
        ]);
    })->name('products');

    Route::get('/orders', function (Request $request) {
        $chunk = app(Ordercontroller::class)->firstChunk($request);

        return view('orders', [
            'firstRows'      => $chunk['rows'],
            'meta'           => $chunk['meta'],
            'ordersTotalEur' => DomainCache::remember(
                DomainCache::ORDERS,
                'orders:total-eur',
                fn () => (float) OrderItem::query()
                    ->selectRaw('COALESCE(SUM(' . OrderItem::COL_AUF_MENGE . ' * ' . OrderItem::COL_KAUF_PREIS . '), 0) as total')
                    ->value('total')
            ),
        ]);
    })->name('orders');

    Route::get('/customers', function (Request $request) {
        $chunk = app(CustomerController::class)->firstChunk($request);

        return view('customers', [
            'firstRows' => $chunk['rows'],
            'meta'      => $chunk['meta'],
        ]);
    })->name('customers');

    Route::get('/warehouse', function (Request $request) {
        $chunk = app(WarehouseGroupController::class)->firstChunk($request);

        return view('warehouse', [
            'firstRows' => $chunk['rows'],
            'meta'      => $chunk['meta'],
        ]);
    })->name('warehouse');

    Route::get('/purchase-orders', function (Request $request) {
        $chunk = app(PurchaseOrderController::class)->firstChunk($request);

        return view('purchase-orders', [
            'firstRows' => $chunk['rows'],
            'meta'      => $chunk['meta'],
        ]);
    })->name('purchase-orders');

    Route::get('/suppliers', function (Request $request) {
        $chunk = app(SupplierController::class)->firstChunk($request);

        return view('suppliers', [
            'firstRows' => $chunk['rows'],
            'meta'      => $chunk['meta'],
        ]);
    })->name('suppliers');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
