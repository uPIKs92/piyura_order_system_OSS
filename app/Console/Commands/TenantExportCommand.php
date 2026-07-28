<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TenantExportCommand extends Command
{
    protected $signature = 'tenant:export {slug : Tenant slug} {--path= : Output JSON file path}';

    protected $description = 'Export tenant data to JSON';

    public function handle(TenantExporter $exporter): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('slug'))->firstOrFail();
        $payload = $exporter->export($tenant);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $path = $this->option('path') ?? storage_path("app/exports/tenant-{$tenant->slug}.json");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $json);

        $this->info("Exported to {$path}");

        return self::SUCCESS;
    }
}
