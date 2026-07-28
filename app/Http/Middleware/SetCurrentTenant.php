<?php

namespace App\Http\Middleware;

use App\Support\LoginTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            app()->instance('currentTenantId', $user->tenant_id);
        } else {
            $tenant = LoginTenant::resolve($request);
            if ($tenant) {
                app()->instance('currentTenantId', $tenant->id);
            }
        }

        return $next($request);
    }
}
