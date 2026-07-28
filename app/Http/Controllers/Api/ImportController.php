<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOrdersImportJob;
use App\Services\ImportTemplateService;
use App\Services\OrdersImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function template(ImportTemplateService $templateService): BinaryFileResponse
    {
        $path = $templateService->ensureExists();

        return response()->download($path, 'orders-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request, OrdersImportService $importService): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'mapping' => 'nullable|in:auto,config,sheets_legacy',
        ]);

        File::ensureDirectoryExists(config('import.path'));

        $stored = $request->file('file')->store('imports');
        $path = Storage::disk('local')->path($stored);
        $user = $request->user();

        $mapping = $request->input('mapping', 'auto');
        if ($mapping === 'auto') {
            $mapping = $importService->detectMapping($path);
        }

        $rowCount = $importService->countRows($path, $mapping);

        if ($rowCount > config('import.queue_threshold', 100)) {
            Bus::dispatch(new ProcessOrdersImportJob($path, $user->id, $mapping));

            return response()->json([
                'queued' => true,
                'rows' => $rowCount,
                'mapping' => $mapping,
                'message' => 'Import queued for processing',
            ], 202);
        }

        $result = $importService->importFile($path, $user, $mapping);
        if (! empty($result['errors'])) {
            $importService->writeErrorReport($result['errors']);
        }

        return response()->json(array_merge($result, ['mapping' => $mapping]));
    }
}
