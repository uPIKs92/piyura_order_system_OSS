<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function daily(?string $date = null): array
    {
        $date = $date ?? now()->toDateString();

        $orders = Order::whereDate('order_date', $date)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->get();

        return $this->summarize($orders, ['from' => $date, 'to' => $date]);
    }

    public function dailyTrend(string $from, string $to): array
    {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->startOfDay();
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        $aggregated = Order::query()
            ->whereDate('order_date', '>=', $from)
            ->whereDate('order_date', '<=', $to)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->select(
                DB::raw('DATE(order_date) as date'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(grand_total), 0) as total_revenue'),
            )
            ->groupBy(DB::raw('DATE(order_date)'))
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $profitAggregated = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('product_units', 'product_units.id', '=', 'order_items.product_unit_id')
            ->whereNull('order_items.deleted_at')
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->when($tenantId, fn ($query) => $query->where('orders.tenant_id', $tenantId))
            ->whereDate('orders.order_date', '>=', $from)
            ->whereDate('orders.order_date', '<=', $to)
            ->select(
                DB::raw('DATE(orders.order_date) as date'),
                DB::raw('COALESCE(SUM(order_items.subtotal), 0) as sales_turnover'),
                DB::raw('COALESCE(SUM(order_items.quantity * COALESCE(product_units.harga_beli, 0)), 0) as purchasing_cost'),
            )
            ->groupBy(DB::raw('DATE(orders.order_date)'))
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $items = [];
        $totals = [
            'sales_turnover' => 0.0,
            'purchasing_cost' => 0.0,
            'net_profit' => 0.0,
            'total_orders' => 0,
            'total_revenue' => 0.0,
        ];

        for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
            $key = $date->toDateString();
            $row = $aggregated->get($key);
            $profitRow = $profitAggregated->get($key);
            $salesTurnover = (float) ($profitRow->sales_turnover ?? 0);
            $purchasingCost = (float) ($profitRow->purchasing_cost ?? 0);
            $netProfit = $salesTurnover - $purchasingCost;
            $totalOrders = (int) ($row->total_orders ?? 0);
            $totalRevenue = (float) ($row->total_revenue ?? 0);

            $items[] = [
                'date' => $key,
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'sales_turnover' => $salesTurnover,
                'purchasing_cost' => $purchasingCost,
                'net_profit' => $netProfit,
            ];

            $totals['sales_turnover'] += $salesTurnover;
            $totals['purchasing_cost'] += $purchasingCost;
            $totals['net_profit'] += $netProfit;
            $totals['total_orders'] += $totalOrders;
            $totals['total_revenue'] += $totalRevenue;
        }

        return [
            'period' => ['from' => $from, 'to' => $to],
            'totals' => $totals,
            'items' => $items,
        ];
    }

    public function byStaff(int $staffId, string $from, string $to): array
    {
        $orders = Order::where('user_id', $staffId)
            ->whereBetween('order_date', [$from, $to])
            ->where('status', '!=', OrderStatus::Cancelled)
            ->get();

        return $this->summarize($orders, ['from' => $from, 'to' => $to]);
    }

    public function allStaff(string $from, string $to): array
    {
        $orders = Order::whereBetween('order_date', [$from, $to])
            ->where('status', '!=', OrderStatus::Cancelled)
            ->with('user:id,name')
            ->get();

        $items = $orders
            ->groupBy('user_id')
            ->map(function ($userOrders, $userId) {
                $user = $userOrders->first()->user;

                return [
                    'staff_id' => (int) $userId,
                    'staff_name' => $user?->name ?? 'Unknown',
                    'total_orders' => $userOrders->count(),
                    'total_revenue' => (float) $userOrders->sum('grand_total'),
                    'total_ppn' => (float) $userOrders->sum('ppn_amount'),
                ];
            })
            ->sortBy('staff_name')
            ->values()
            ->all();

        return array_merge($this->summarize($orders, ['from' => $from, 'to' => $to]), [
            'all' => true,
            'items' => $items,
        ]);
    }

    public function allProducts(string $from, string $to): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.order_date', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereNull('order_items.deleted_at')
            ->whereNull('orders.deleted_at')
            ->select(
                'order_items.product_id',
                'order_items.product_name',
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
            )
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderBy('order_items.product_name')
            ->get();

        $items = $rows->map(fn ($row) => [
            'product_id' => (int) $row->product_id,
            'product_name' => $row->product_name,
            'total_orders' => (int) $row->total_orders,
            'total_revenue' => (float) $row->total_revenue,
        ])->all();

        $orderCount = Order::whereBetween('order_date', [$from, $to])
            ->where('status', '!=', OrderStatus::Cancelled)
            ->count();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'all' => true,
            'items' => $items,
            'total_orders' => $orderCount,
            'total_revenue' => (float) collect($items)->sum('total_revenue'),
        ];
    }

    public function byProduct(int $productId, string $from, string $to): array
    {
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        $stats = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $productId)
            ->whereBetween('orders.order_date', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereNull('order_items.deleted_at')
            ->whereNull('orders.deleted_at')
            ->when($tenantId, fn ($query) => $query->where('orders.tenant_id', $tenantId))
            ->select(
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('COALESCE(SUM(order_items.subtotal), 0) as total_revenue'),
            )
            ->first();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'product_id' => $productId,
            'total_orders' => (int) ($stats->total_orders ?? 0),
            'total_revenue' => (float) ($stats->total_revenue ?? 0),
        ];
    }

    public function byStatus(): array
    {
        return Order::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();
    }

    public function taxReport(int $year): array
    {
        $orders = Order::whereYear('order_date', $year)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->get();

        return [
            'year' => $year,
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('grand_total'),
            'total_ppn' => $orders->sum('ppn_amount'),
        ];
    }

    private function summarize($orders, array $period): array
    {
        return [
            'period' => $period,
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('grand_total'),
            'total_ppn' => $orders->sum('ppn_amount'),
            'by_status' => $orders->groupBy(fn ($o) => $o->status->value)->map->count(),
        ];
    }
}
