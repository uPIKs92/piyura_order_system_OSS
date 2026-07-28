<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TenantExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantExportController extends Controller
{
    public function export(Request $request, TenantExporter $exporter): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $tenant = $request->user()->tenant;

        return response()->json($exporter->export($tenant));
    }
}
