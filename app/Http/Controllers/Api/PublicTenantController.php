<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\LoginTenant;
use Illuminate\Http\JsonResponse;

class PublicTenantController extends Controller
{
    public function current(): JsonResponse
    {
        $tenant = LoginTenant::resolve();

        if (! $tenant) {
            abort(404);
        }

        return response()->json($tenant->toLoginBrandingArray());
    }
}
