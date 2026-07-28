<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        $backups = Backup::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($backups);
    }

    public function run(): JsonResponse
    {
        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);
        $latest = Backup::orderByDesc('created_at')->first();

        return response()->json([
            'success' => $exitCode === 0,
            'output' => Artisan::output(),
            'backup' => $latest,
        ]);
    }

    public function download(Backup $backup): StreamedResponse
    {
        if ($backup->status !== 'success' || ! $backup->path) {
            abort(404, 'Backup not available');
        }

        $disk = Storage::disk($backup->disk);

        if (! $disk->exists($backup->path)) {
            abort(404, 'Backup file not found');
        }

        return $disk->download($backup->path, $backup->name);
    }
}
