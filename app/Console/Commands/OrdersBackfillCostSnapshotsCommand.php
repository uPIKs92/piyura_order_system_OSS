<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OrdersBackfillCostSnapshotsCommand extends Command
{
    protected $signature = 'orders:backfill-cost-snapshots {--tenant= : Limit backfill to tenant slug}';

    protected $description = 'Backfill order_items.cost_snapshot from product_units.harga_beli';

    public function handle(): int
    {
        $tenantSlug = $this->option('tenant');
        $tenantIds = $tenantSlug
            ? Tenant::where('slug', $tenantSlug)->pluck('id')
            : Tenant::pluck('id');

        if ($tenantIds->isEmpty()) {
            $this->error($tenantSlug ? "Tenant not found: {$tenantSlug}" : 'No tenants found.');

            return self::FAILURE;
        }

        $updated = 0;

        foreach ($tenantIds as $tenantId) {
            $rows = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->leftJoin('product_units', 'product_units.id', '=', 'order_items.product_unit_id')
                ->where('orders.tenant_id', $tenantId)
                ->whereNull('order_items.deleted_at')
                ->where(function ($query) {
                    $query->where('order_items.cost_snapshot', 0)
                        ->orWhereNull('order_items.cost_snapshot');
                })
                ->select(
                    'order_items.id',
                    DB::raw('COALESCE(product_units.harga_beli, 0) as cost'),
                )
                ->get();

            foreach ($rows as $row) {
                DB::table('order_items')
                    ->where('id', $row->id)
                    ->update(['cost_snapshot' => $row->cost]);
            }

            $count = $rows->count();
            $updated += $count;
            $this->line("Tenant {$tenantId}: updated {$count} order items.");
        }

        $this->info("Backfill complete. Updated {$updated} order items.");

        return self::SUCCESS;
    }
}
