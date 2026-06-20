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
            'status'      => 'operational',
            'database'    => $dbStatus,
            'server_time' => now()->toDateTimeString(),
        ]);
    }
}
