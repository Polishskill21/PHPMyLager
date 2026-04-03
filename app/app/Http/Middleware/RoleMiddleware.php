<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (!Auth::check()) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        if (!in_array(Auth::user()->role, $roles)) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Forbidden. Insufficient permissions.'], 403)
                : abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}