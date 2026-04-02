<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\ProductController;

Route::get('/status', [SystemController::class, 'status']);

//products route
Route::apiResource('products', ProductController::class);
