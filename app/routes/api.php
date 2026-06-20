<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\WarehouseGroups\WarehouseGroupController;
use App\Http\Controllers\Orders\Ordercontroller;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\PurchaseOrders\PurchaseOrderController;
use App\Http\Controllers\Suppliers\SupplierController;

Route::get('/status', [SystemController::class, 'status']);

//auth:sanctum could be used later
Route::middleware(['web', 'auth'])->group(function () {

    // All roles can read
    Route::get('products',                     [ProductController::class, 'index'])->name('products.index');
    Route::get('products/page',                [ProductController::class, 'page'])->name('products.page');
    Route::get('products/{product}',           [ProductController::class, 'show'])->name('products.show');
    Route::get('products/{product}/stock-history', [ProductController::class, 'stockHistory']);

    Route::get('warehouse-groups',             [WarehouseGroupController::class, 'index'])->name('warehouse-groups.index');
    Route::get('warehouse-groups/page',        [WarehouseGroupController::class, 'page'])->name('warehouse-groups.page');
    Route::get('warehouse-groups/{id}',        [WarehouseGroupController::class, 'show'])->name('warehouse-groups.show');
    Route::get('warehouse-groups/{id}/products', [WarehouseGroupController::class, 'products']);

    Route::get('orders',                       [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/page',                  [OrderController::class, 'page'])->name('orders.page');
    Route::get('orders/{order}',               [OrderController::class, 'show'])->name('orders.show');

    Route::get('customers',                    [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/page',               [CustomerController::class, 'page'])->name('customers.page');
    Route::get('customers/{customer}',         [CustomerController::class, 'show'])->name('customers.show');

    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('purchase-orders/page', [PurchaseOrderController::class, 'page'])->name('purchase-orders.page');
    Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');

    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('suppliers/page', [SupplierController::class, 'page'])->name('suppliers.page');
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');

    // Admin + writer can create and update
    Route::middleware('role:admin,writer')->group(function () {
        Route::post('products',                [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}',       [ProductController::class, 'update'])->name('products.update');
        // Route::patch('products/{product}',[ProductController::class, 'update']);

        Route::post('warehouse-groups',        [WarehouseGroupController::class, 'store'])->name('warehouse-groups.store');
        Route::put('warehouse-groups/{id}',    [WarehouseGroupController::class, 'update'])->name('warehouse-groups.update');

        Route::post('orders',                  [OrderController::class, 'store'])->name('orders.store');
        Route::put('orders/{order}',           [OrderController::class, 'update'])->name('orders.update');

        Route::post('customers',               [CustomerController::class, 'store'])->name('customers.store');
        Route::put('customers/{customer}',     [CustomerController::class, 'update'])->name('customers.update');

        Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::patch('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receiveDelivery'])->name('purchase-orders.receive');

        
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    });

    // Admin only can delete
    Route::middleware('role:admin')->group(function () {
        Route::patch('products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');

        Route::delete('products/{product}',    [ProductController::class, 'destroy'])->name('products.destroy');
        Route::delete('orders/{order}',        [OrderController::class, 'destroy'])->name('orders.destroy');
        Route::delete('customers/{customer}',  [CustomerController::class, 'destroy'])->name('customers.destroy');
        // Route::delete('warehouse-groups/{id}', [WarehouseGroupController::class, 'destroy'])->name('warehouse-groups.destroy');

        Route::delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy');

        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });
});
