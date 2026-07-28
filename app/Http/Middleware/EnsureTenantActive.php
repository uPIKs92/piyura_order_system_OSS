<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->user()?->tenant;

        if ($tenant?->is_suspended) {
            return response()->json(['message' => 'This business account is suspended.'], 403);
        }

        return $next($request);
    }
}
