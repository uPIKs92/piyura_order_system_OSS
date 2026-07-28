<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Closure;

class PreventRequestsDuringMaintenanceWithIpWhitelist extends PreventRequestsDuringMaintenance
{
    public function handle($request, Closure $next)
    {
        if ($this->app->maintenanceMode()->active()) {
            $allowed = config('maintenance.allowed_ips', []);
            if ($allowed && in_array($request->ip(), $allowed, true)) {
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }
}
