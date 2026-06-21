<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BypassAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (app()->environment('local')) {
            if ($request->hasHeader('X-Debug-Role')) {
                $role = $request->header('X-Debug-Role');
                $user = User::where('role', $role)->first();

                if ($user) {
                    Auth::setUser($user);
                }
            }
        }

        return $next($request);
    }
}