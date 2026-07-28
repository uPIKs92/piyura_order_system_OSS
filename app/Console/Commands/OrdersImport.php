<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOrdersImportJob;
use App\Models\User;
use App\Services\OrdersImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;

class OrdersImport extends Command
{
    protected $signature = 'orders:import {filename} {--user= : Owner user ID to assign orders} {--mapping=auto : config, sheets_legacy, or auto}';

    protected $description = 'Import orders from CSV/XLSX file in storage/app/imports';

    public function handle(OrdersImportService $importService): int
    {
        $path = config('import.path').'/'.$this->argument('filename');
        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $mapping = $this->option('mapping') ?? 'auto';
        if (! in_array($mapping, ['auto', 'config', 'sheets_legacy'], true)) {
            $this->error('Invalid mapping. Use auto, config, or sheets_legacy.');

            return self::FAILURE;
        }

        $userId = $this->option('user') ?? User::where('role', 'owner')->value('id');
        $user = User::findOrFail($userId);

        if ($mapping === 'auto') {
            $mapping = $importService->detectMapping($path);
        }

        $rowCount = $importService->countRows($path, $mapping);

        if ($rowCount > config('import.queue_threshold', 100)) {
            Bus::dispatch(new ProcessOrdersImportJob($path, $user->id, $mapping));
            $this->info("Queued import of {$rowCount} rows.");

            return self::SUCCESS;
        }

        $result = $importService->importFile($path, $user, $mapping);

        if ($result['errors']) {
            $report = $importService->writeErrorReport($result['errors']);
            $this->warn("Error report: {$report}");
        }

        $this->info("Import complete: {$result['total']} total, {$result['success']} success, {$result['failed']} failed.");
        if ($result['errors']) {
            $this->table(['Row', 'Field', 'Reason'], collect($result['errors'])->map(fn ($e) => [$e['row'], $e['field'], $e['reason']]));
        }

        return self::SUCCESS;
    }
}
