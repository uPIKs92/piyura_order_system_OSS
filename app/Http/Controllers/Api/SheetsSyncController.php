<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SheetsSyncOutbox;
use App\Services\SheetsSyncService;
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

    public function retry(SheetsSyncOutbox $sheetsSync, SheetsSyncService $sheetsSyncService): JsonResponse
    {
        abort_unless($sheetsSync->status === 'failed', 422, 'Only failed records can be retried.');

        $record = $sheetsSyncService->retry($sheetsSync);

        return response()->json($record);
    }
}
