<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SystemController;

Route::get('/status', [SystemController::class, 'status']);