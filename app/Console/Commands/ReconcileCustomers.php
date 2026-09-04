<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileCustomers extends Command
{
    protected $signature = 'customers:reconcile';

    protected $description = 'Rebuild the customer directory from orders: replay phone-identified orders, prune guest-derived phoneless rows, and refresh orders_count / last_ordered_at. Caveat: a manually added phoneless customer whose name matches phoneless (guest) orders is treated as guest-derived, will be pruned, and can be re-added.';

    private const CHUNK_SIZE = 500;

    public function handle(): int
    {
        $totals = ['tenants' => 0, 'replayed' => 0, 'pruned' => 0, 'counters' => 0];

        Tenant::query()
            ->orderBy('id')
            ->chunkById(100, function ($tenants) use (&$totals) {
                foreach ($tenants as $tenant) {
                    app()->instance('currentTenantId', $tenant->id);

                    $replayed = $this->replayOrders($tenant->id);
                    $pruned = $this->pruneGuestRows($tenant->id);
                    $counters = $this->refreshCounters($tenant->id);

                    $totals['tenants']++;
                    $totals['replayed'] += $replayed;
                    $totals['pruned'] += $pruned;
                    $totals['counters'] += $counters;

                    $this->info("[{$tenant->slug}] replayed={$replayed} pruned={$pruned} counters_updated={$counters}");
                }
            });

        app()->forgetInstance('currentTenantId');

        $this->info("Reconciled {$totals['tenants']} tenant(s): {$totals['replayed']} order(s) replayed, {$totals['pruned']} guest customer(s) pruned, {$totals['counters']} customer counter(s) updated.");

        return self::SUCCESS;
    }

    private function replayOrders(int $tenantId): int
    {
        $replayed = 0;
        $lastDate = null;
        $lastId = 0;

        do {
            $orders = Order::query()
                ->where('tenant_id', $tenantId)
                ->whereRaw("TRIM(COALESCE(customer_phone, '')) <> ''")
                ->when($lastDate !== null, fn ($query) => $query->where(function ($query) use ($lastDate, $lastId) {
                    $query->where('order_date', '>', $lastDate)
                        ->orWhere(fn ($query) => $query
                            ->where('order_date', $lastDate)
                            ->where('id', '>', $lastId));
                }))
                ->orderBy('order_date')
                ->orderBy('id')
                ->limit(self::CHUNK_SIZE)
                ->get();

            foreach ($orders as $order) {
                Customer::upsertFromOrder($order);
                $lastDate = $order->getRawOriginal('order_date');
                $lastId = $order->getKey();
                $replayed++;
            }
        } while ($orders->count() === self::CHUNK_SIZE);

        return $replayed;
    }

    private function pruneGuestRows(int $tenantId): int
    {
        $nameKeys = [];
        $lastNameKey = null;

        do {
            $rows = Order::query()
                ->where('tenant_id', $tenantId)
                ->whereRaw("TRIM(COALESCE(customer_phone, '')) = ''")
                ->whereRaw("TRIM(COALESCE(customer_name, '')) <> ''")
                ->when($lastNameKey !== null, fn ($query) => $query->whereRaw('LOWER(TRIM(customer_name)) > ?', [$lastNameKey]))
                ->selectRaw('DISTINCT LOWER(TRIM(customer_name)) as name_key')
                ->orderByRaw('LOWER(TRIM(customer_name))')
                ->limit(self::CHUNK_SIZE)
                ->get();

            foreach ($rows as $row) {
                $nameKeys[] = $row->name_key;
                $lastNameKey = $row->name_key;
            }
        } while ($rows->count() === self::CHUNK_SIZE);

        $pruned = 0;

        foreach (array_chunk($nameKeys, self::CHUNK_SIZE) as $keys) {
            $pruned += Customer::query()
                ->where('tenant_id', $tenantId)
                ->whereRaw("TRIM(COALESCE(phone, '')) = ''")
                ->whereIn(DB::raw('LOWER(TRIM(name))'), $keys)
                ->delete();
        }

        return $pruned;
    }

    private function refreshCounters(int $tenantId): int
    {
        $updated = 0;

        Customer::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("TRIM(COALESCE(phone, '')) <> ''")
            ->select('id', 'phone')
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($customers) use ($tenantId, &$updated) {
                $phones = $customers
                    ->map(fn ($customer) => trim((string) $customer->phone))
                    ->unique()
                    ->values()
                    ->all();

                $stats = Order::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn(DB::raw('TRIM(customer_phone)'), $phones)
                    ->selectRaw('TRIM(customer_phone) as phone, COUNT(*) as orders_count, MAX(order_date) as last_ordered_at')
                    ->groupByRaw('TRIM(customer_phone)')
                    ->get()
                    ->mapWithKeys(fn ($row) => [$row->phone => $row]);

                foreach ($customers as $customer) {
                    $stat = $stats->get(trim((string) $customer->phone));

                    Customer::query()
                        ->whereKey($customer->getKey())
                        ->toBase()
                        ->update([
                            'orders_count' => $stat->orders_count ?? 0,
                            'last_ordered_at' => $stat->last_ordered_at ?? null,
                        ]);

                    $updated++;
                }
            });

        return $updated;
    }
}
