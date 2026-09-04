<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\GoogleSheetsService;
use App\Services\OrdersImportService;
use App\Support\TenantSettings;
use Illuminate\Console\Command;

class OrdersRepairImportStatusCommand extends Command
{
    protected $signature = 'orders:repair-import-status
        {--tenant= : Tenant slug}
        {--file= : Optional workbook filename in storage/app/imports}
        {--from-sheets : Read order rows from connected Google Sheets}';

    protected $description = 'Repair imported order status and payment state from Sheets rows';

    public function handle(OrdersImportService $importService, GoogleSheetsService $sheets): int
    {
        $tenantSlug = $this->option('tenant');
        if (! $tenantSlug) {
            $this->error('Tenant slug is required via --tenant=');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
        if (! $tenant) {
            $this->error("Tenant not found: {$tenantSlug}");

            return self::FAILURE;
        }

        $owner = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', 'owner')
            ->first();

        if (! $owner) {
            $this->error('Tenant owner not found.');

            return self::FAILURE;
        }

        app()->instance('currentTenantId', $tenant->id);

        if ($this->option('from-sheets')) {
            $settings = TenantSettings::for($tenant->id);
            if (! $settings->googleConnected() || ! $settings->sheetsSpreadsheetId()) {
                $this->error('Google Sheets is not connected for this tenant.');

                return self::FAILURE;
            }

            $rows = $sheets->readTab(
                $tenant->id,
                (string) $settings->sheetsSpreadsheetId(),
                $settings->sheetsOrdersTab(),
            );
        } elseif ($file = $this->option('file')) {
            $path = config('import.path').'/'.$file;
            $rows = $importService->readOrdersSheetRows($path);
        } else {
            $this->error('Provide --from-sheets or --file=');

            return self::FAILURE;
        }

        $result = $importService->repairOrderStatusesFromRows($rows, $owner, 2, $tenant->id);

        $this->info("Rows scanned: {$result['total']}");
        $this->info("Updated: {$result['updated']}");
        $this->info("Payments added: {$result['paid']}");
        $this->info("Unchanged: {$result['skipped']}");
        $this->info("Missing orders: {$result['missing']}");

        if (! empty($result['errors'])) {
            $this->table(['Row', 'Field', 'Reason'], collect($result['errors'])->map(
                fn (array $error) => [$error['row'], $error['field'], $error['reason']],
            ));
        }

        return self::SUCCESS;
    }
}
