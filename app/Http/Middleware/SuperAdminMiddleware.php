<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->user() || $request->user()->role_id !== 1) {
            return response()->json([
                'message' => 'Only a Super Admin can access this dashboard.',
            ], 403);
        }

        return $next($request);
    }
}
