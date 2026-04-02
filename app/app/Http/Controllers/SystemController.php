<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Exception;

class SystemController extends Controller
{
    public function status()
    {
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (Exception $e) {
            $dbStatus = 'disconnected';
        }

        return response()->json([
            'status' => 'operational',
            'environment' => app()->environment(),
            'database' => $dbStatus,
            'server_time' => now()->toDateTimeString(),
            'php_version' => phpversion(),
            'laravel_version' => app()->version(),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB'
        ]);
    }
}
