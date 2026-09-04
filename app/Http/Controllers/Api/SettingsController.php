<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MayarService;
use App\Support\BackupSettings;
use App\Support\ExpirySettings;
use App\Support\PaymentSettings;
use App\Support\PpnSettings;
use App\Support\TenantSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function pajak(): JsonResponse
    {
        return response()->json(TenantSettings::for()->pajakToArray());
    }

    public function updatePajak(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'npwp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9.\-]+$/'],
            'pph_mode' => 'nullable|in:umkm_non_pkp,umkm_pkp_22',
        ]);

        TenantSettings::for()->updatePajak($validated);

        return response()->json(TenantSettings::for()->pajakToArray());
    }

    public function delivery(): JsonResponse
    {
        return response()->json(TenantSettings::for()->deliveryToArray());
    }

    public function updateDelivery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fee_mode' => 'nullable|in:per_km,fixed',
            'fee_per_km' => 'nullable|numeric|between:0,100000000',
            'min_fee' => 'nullable|numeric|between:0,100000000',
            'fixed_fee' => 'nullable|numeric|between:0,100000000',
            'google_maps_api_key' => 'nullable|string|max:255',
        ]);

        TenantSettings::for()->updateDelivery($validated);

        return response()->json(TenantSettings::for()->deliveryToArray());
    }

    public function expiry(): JsonResponse
    {
        return response()->json(ExpirySettings::toArray());
    }

    public function updateExpiry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alert_days' => 'required|integer|min:1|max:365',
        ]);

        ExpirySettings::update((int) $validated['alert_days']);

        return response()->json(ExpirySettings::toArray());
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

    public function payment(): JsonResponse
    {
        return response()->json(PaymentSettings::toArray());
    }

    public function updatePayment(Request $request): JsonResponse
    {
        if (! $request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'bank_name' => 'nullable|string|max:120',
            'bank_account_name' => 'nullable|string|max:120',
            'bank_account_number' => 'nullable|string|max:60',
            'mayar_api_key' => 'nullable|string|max:255',
        ]);

        // Empty API key string means "leave unchanged"; only persist when provided.
        if (array_key_exists('mayar_api_key', $validated) && $validated['mayar_api_key'] === '') {
            unset($validated['mayar_api_key']);
        }

        TenantSettings::for()->updatePayment($validated);

        return response()->json(PaymentSettings::toArray());
    }

    public function uploadQris(Request $request): JsonResponse
    {
        if (! $request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'qris' => 'required|file|max:2048',
        ]);

        $tenant = $request->user()->tenant;
        $settings = TenantSettings::for($tenant->id);
        $file = $request->file('qris');
        $allowed = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        $mime = $file->getMimeType() ?: '';
        if (! isset($allowed[$mime])) {
            return response()->json(['message' => 'Invalid QRIS file type.'], 422);
        }

        $ext = $allowed[$mime];
        $path = "tenants/{$tenant->id}/qris.{$ext}";

        if ($old = $settings->qrisImagePath()) {
            Storage::disk('public')->delete($old);
        }

        $file->storeAs("tenants/{$tenant->id}", "qris.{$ext}", 'public');
        $settings->setQrisImage($path);

        return response()->json(PaymentSettings::toArray());
    }

    public function deleteQris(Request $request): JsonResponse
    {
        if (! $request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $tenant = $request->user()->tenant;
        $settings = TenantSettings::for($tenant->id);

        if ($path = $settings->qrisImagePath()) {
            Storage::disk('public')->delete($path);
            $settings->clearQrisImage();
        }

        return response()->json(PaymentSettings::toArray());
    }

    public function testMayar(Request $request): JsonResponse
    {
        if (! $request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $tenant = $request->user()->tenant;
        $connected = MayarService::forTenant($tenant->id)->healthCheck();

        return response()->json([
            'connected' => $connected,
            'message' => $connected ? 'Koneksi Mayar berhasil.' : 'Gagal terhubung ke Mayar. Periksa API key.',
        ]);
    }
}
