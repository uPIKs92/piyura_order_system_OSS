<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Http\Request;

class LoginTenant
{
    public static function resolve(?Request $request = null): ?Tenant
    {
        return Tenant::query()->orderBy('id')->first();
    }
}
