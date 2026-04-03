<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BypassAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (app()->environment('local')) {
            $role = $request->header('X-Debug-Role', 'admin');
            $user = User::where('role', $role)->first();

            if ($user) {
                Auth::setUser($user);
            }
        }

        return $next($request);
    }
}