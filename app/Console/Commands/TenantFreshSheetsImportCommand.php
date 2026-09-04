<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\GoogleSheetsService;
use App\Services\OrdersImportService;
use App\Services\TenantImportResetService;
use App\Support\TenantSettings;
use Illuminate\Console\Command;

class TenantFreshSheetsImportCommand extends Command
{
    protected $signature = 'tenant:fresh-sheets-import
        {slug : Tenant slug}
        {--force : Skip confirmation prompt}';

    protected $description = 'Wipe tenant catalog/orders and import fresh data from Google Sheets';

    public function handle(
        TenantImportResetService $resetService,
        OrdersImportService $importService,
        GoogleSheetsService $sheets,
    ): int {
        $tenant = Tenant::query()->where('slug', $this->argument('slug'))->first();
        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $settings = TenantSettings::for($tenant->id);
        if (! $settings->googleConnected() || ! $settings->sheetsSpreadsheetId()) {
            $this->error('Google Sheets is not connected or spreadsheet ID is missing.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Delete all orders/products for {$tenant->name} and re-import from Sheets?")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
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

        $deleted = $resetService->resetBusinessData($tenant->id);
        $this->info('Deleted: '.json_encode($deleted));

        $spreadsheetId = (string) $settings->sheetsSpreadsheetId();
        $productRows = $sheets->readTab($tenant->id, $spreadsheetId, $settings->sheetsProductsTab());
        $orderRows = $sheets->readTab($tenant->id, $spreadsheetId, $settings->sheetsOrdersTab());

        $products = $importService->importProductsRows($productRows, $tenant->id);
        $orders = $importService->importOrdersRows($orderRows, $owner, 2, $tenant->id, true);

        $this->info('Products: '.json_encode($products));
        $this->info('Orders: '.json_encode($orders));

        return self::SUCCESS;
    }
}
