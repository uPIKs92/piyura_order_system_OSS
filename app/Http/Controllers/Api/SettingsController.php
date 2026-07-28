<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\BackupSettings;
use App\Support\PpnSettings;
use App\Support\TenantSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function ppn(): JsonResponse
    {
        return response()->json(PpnSettings::toArray());
    }

    public function updatePpn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'percentage' => 'required|numeric|min:0|max:100',
        ]);

        PpnSettings::update($validated['enabled'], (float) $validated['percentage']);

        return response()->json(PpnSettings::toArray());
    }

    public function integrations(): JsonResponse
    {
        return response()->json(TenantSettings::for()->integrationsToArray());
    }

    public function updateIntegrations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sheets_sync_enabled' => 'required|boolean',
            'sheets_spreadsheet_id' => 'nullable|string|max:255',
            'sheets_products_tab' => 'nullable|string|max:100',
            'sheets_orders_tab' => 'nullable|string|max:100',
            'sheets_reporting_tab' => 'nullable|string|max:100',
        ]);

        TenantSettings::for()->updateIntegrations($validated);

        return response()->json(TenantSettings::for()->integrationsToArray());
    }

    public function backup(): JsonResponse
    {
        return response()->json(BackupSettings::toArray());
    }
}
