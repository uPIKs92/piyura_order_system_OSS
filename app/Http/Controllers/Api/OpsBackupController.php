<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class OpsBackupController extends Controller
{
    public function store(): JsonResponse
    {
        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);
        $latest = Backup::orderByDesc('created_at')->first();

        if ($exitCode !== 0) {
            Log::warning('Ops backup failed', ['output' => Artisan::output()]);
        }

        return response()->json([
            'success' => $exitCode === 0,
            'backup' => $latest ? $latest->only(['id', 'name', 'status', 'created_at']) : null,
        ]);
    }
}
