<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SheetsSyncOutbox;
use App\Services\GoogleSheetsService;
use App\Services\SheetsSyncService;
use App\Support\TenantSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SheetsSyncController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'failed');

        $records = SheetsSyncOutbox::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with('order:id,invoice_no')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($records);
    }

    public function dispatch(GoogleSheetsService $sheets, SheetsSyncService $sheetsSyncService): JsonResponse
    {
        $tenantId = (int) app('currentTenantId');

        if (! $sheets->isReadyForSync($tenantId)) {
            $settings = TenantSettings::for($tenantId);

            if (! $settings->googleConnected()) {
                $message = 'Google belum terhubung. Hubungkan Google terlebih dahulu.';
            } elseif (blank($settings->sheetsSpreadsheetId())) {
                $message = 'Spreadsheet ID masih kosong. Isi Spreadsheet ID terlebih dahulu.';
            } else {
                $message = 'Sync order aktif masih mati. Aktifkan "Sync order aktif" lalu simpan integrasi.';
            }

            return response()->json(['message' => $message], 422);
        }

        return response()->json($sheetsSyncService->dispatchForTenant($tenantId));
    }

    public function retry(SheetsSyncOutbox $sheetsSync, SheetsSyncService $sheetsSyncService): JsonResponse
    {
        abort_unless($sheetsSync->status === 'failed', 422, 'Only failed records can be retried.');

        $record = $sheetsSyncService->retry($sheetsSync);

        return response()->json($record);
    }
}
