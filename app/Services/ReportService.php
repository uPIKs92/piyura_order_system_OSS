<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\AppTime;
use App\Support\PphUkm;
use App\Support\TenantSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function daily(?string $date = null): array
    {
        $date = $date ?? AppTime::toDateString();

        return OrderService::rememberAggregate("daily.$date", function () use ($date) {
            $report = $this->summarizeRange(
                ['from' => $date, 'to' => $date],
                fn ($query) => $query->whereDate('order_date', $date),
            );

            $returnsTotal = (float) $this->refundBaseQuery()
                // returns.created_at is stored UTC; select it by the WIB day's
                // UTC window (portable across MySQL/SQLite).
                ->where('returns.created_at', '>=', AppTime::dayStartUtc($date))
                ->where('returns.created_at', '<=', AppTime::dayEndUtc($date))
                ->selectRaw($this->returnsTotalSelect())
                ->value('returns_total');

            $profitRow = $this->profitRows($date, $date)->get($date);
            $netProfit = (float) ($profitRow->sales_turnover ?? 0)
                - (float) ($profitRow->purchasing_cost ?? 0);

            $expensesTotal = (float) ($this->expenseRows($date, $date)->get($date)->expenses_total ?? 0);

            return array_merge($report, [
                'returns_total' => $returnsTotal,
                'net_revenue' => (float) $report['total_revenue'] - $returnsTotal,
                'net_profit' => $netProfit,
                'expenses_total' => $expensesTotal,
                'laba_bersih' => $netProfit - $expensesTotal,
            ]);
        });
    }

    public function dailyTrend(string $from, string $to): array
    {
        return OrderService::rememberAggregate("trend.$from.$to", function () use ($from, $to) {
            return $this->computeDailyTrend($from, $to);
        });
    }

    private function computeDailyTrend(string $from, string $to): array
    {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->startOfDay();
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        $aggregated = Order::query()
            ->whereDate('order_date', '>=', $from)
            ->whereDate('order_date', '<=', $to)
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Draft])
            ->select(
                DB::raw('DATE(order_date) as date'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(grand_total), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(orders.subtotal), 0) as gross_subtotal'),
                DB::raw('COALESCE(SUM(orders.discount_total), 0) as total_discount'),
            )
            ->groupBy(DB::raw('DATE(order_date)'))
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $profitAggregated = $this->profitRows($from, $to);

        $expensesAggregated = $this->expenseRows($from, $to);

        // Refunds are attributed to the return's own date (WIB), not the
        // order date. created_at is stored UTC, so select by the range's UTC
        // window and bucket per WIB day in PHP (CONVERT_TZ isn't portable).
        $returnRows = $this->refundBaseQuery()
            ->where('returns.created_at', '>=', AppTime::dayStartUtc($from))
            ->where('returns.created_at', '<=', AppTime::dayEndUtc($to))
            ->groupBy('returns.id', 'returns.created_at')
            ->select('returns.created_at', DB::raw($this->returnsTotalSelect()))
            ->get();

        $returnAggregated = $returnRows
            ->groupBy(fn ($row) => Carbon::parse($row->created_at, 'UTC')->setTimezone(AppTime::TZ)->toDateString())
            ->map(fn ($rows) => (object) ['returns_total' => (float) $rows->sum('returns_total')]);

        $items = [];
        $totals = [
            'sales_turnover' => 0.0,
            'purchasing_cost' => 0.0,
            'net_profit' => 0.0,
            'expenses_total' => 0.0,
            'laba_bersih' => 0.0,
            'total_orders' => 0,
            'total_revenue' => 0.0,
            'gross_subtotal' => 0.0,
            'total_discount' => 0.0,
            'returns_total' => 0.0,
        ];

        for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
            $key = $date->toDateString();
            $row = $aggregated->get($key);
            $profitRow = $profitAggregated->get($key);
            $salesTurnover = (float) ($profitRow->sales_turnover ?? 0);
            $purchasingCost = (float) ($profitRow->purchasing_cost ?? 0);
            $netProfit = $salesTurnover - $purchasingCost;
            $expensesTotal = (float) ($expensesAggregated->get($key)->expenses_total ?? 0);
            $totalOrders = (int) ($row->total_orders ?? 0);
            $totalRevenue = (float) ($row->total_revenue ?? 0);
            $grossSubtotal = (float) ($row->gross_subtotal ?? 0);
            $totalDiscount = (float) ($row->total_discount ?? 0);
            $returnsTotal = (float) ($returnAggregated->get($key)->returns_total ?? 0);
            $aov = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0.0;

            $items[] = [
                'date' => $key,
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'sales_turnover' => $salesTurnover,
                'purchasing_cost' => $purchasingCost,
                'net_profit' => $netProfit,
                'expenses_total' => $expensesTotal,
                'laba_bersih' => $netProfit - $expensesTotal,
                'gross_subtotal' => $grossSubtotal,
                'total_discount' => $totalDiscount,
                'returns_total' => $returnsTotal,
                'net_revenue' => $totalRevenue - $returnsTotal,
                'aov' => $aov,
            ];

            $totals['sales_turnover'] += $salesTurnover;
            $totals['purchasing_cost'] += $purchasingCost;
            $totals['net_profit'] += $netProfit;
            $totals['expenses_total'] += $expensesTotal;
            $totals['laba_bersih'] += $netProfit - $expensesTotal;
            $totals['total_orders'] += $totalOrders;
            $totals['total_revenue'] += $totalRevenue;
            $totals['gross_subtotal'] += $grossSubtotal;
            $totals['total_discount'] += $totalDiscount;
            $totals['returns_total'] += $returnsTotal;
        }

        $totals['aov'] = $totals['total_orders'] > 0 ? $totals['total_revenue'] / $totals['total_orders'] : 0.0;
        $totals['net_revenue'] = $totals['total_revenue'] - $totals['returns_total'];

        return [
            'period' => ['from' => $from, 'to' => $to],
            'totals' => $totals,
            'items' => $items,
        ];
    }

    public function byStaff(int $staffId, string $from, string $to): array
    {
        return OrderService::rememberAggregate("staff.$staffId.$from.$to", function () use ($staffId, $from, $to) {
            return $this->summarizeRange(
                ['from' => $from, 'to' => $to],
                fn ($query) => $query
                    ->where('user_id', $staffId)
                    ->whereBetween('order_date', [$from, $to]),
            );
        });
    }

    public function allStaff(string $from, string $to): array
    {
        return OrderService::rememberAggregate("staff.all.$from.$to", function () use ($from, $to) {
            return $this->computeAllStaff($from, $to);
        });
    }

    private function computeAllStaff(string $from, string $to): array
    {
        $rows = $this->ordersBaseQuery()
            ->whereBetween('order_date', [$from, $to])
            ->select('user_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(ppn_amount), 0) as total_ppn')
            ->selectRaw('COALESCE(SUM(CASE WHEN grand_total > total_paid THEN 1 ELSE 0 END), 0) as unpaid_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN grand_total > total_paid THEN grand_total - total_paid ELSE 0 END), 0) as unpaid_amount')
            ->groupBy('user_id')
            ->get();

        $names = User::query()
            ->whereIn('id', $rows->pluck('user_id'))
            ->get(['id', 'name'])
            ->keyBy('id');

        $items = $rows
            ->map(fn ($row) => [
                'staff_id' => (int) $row->user_id,
                'staff_name' => $names->get($row->user_id)?->name ?? 'Unknown',
                'total_orders' => (int) $row->total_orders,
                'total_revenue' => (float) $row->total_revenue,
                'total_ppn' => (float) $row->total_ppn,
                'unpaid_count' => (int) $row->unpaid_count,
                'unpaid_amount' => (float) $row->unpaid_amount,
                'aov' => (int) $row->total_orders > 0 ? (float) $row->total_revenue / (int) $row->total_orders : 0.0,
            ])
            ->sortBy([
                ['total_revenue', 'desc'],
                ['staff_name', 'asc'],
            ])
            ->values()
            ->all();

        return array_merge(
            $this->summarizeRange(
                ['from' => $from, 'to' => $to],
                fn ($query) => $query->whereBetween('order_date', [$from, $to]),
            ),
            [
                'all' => true,
                'items' => $items,
            ],
        );
    }

    public function allProducts(string $from, string $to): array
    {
        return OrderService::rememberAggregate("products.all.$from.$to", function () use ($from, $to) {
            return $this->computeAllProducts($from, $to);
        });
    }

    private function computeAllProducts(string $from, string $to): array
    {
        $sales = $this->productSalesRows($from, $to);
        $returnAgg = $this->productReturnRows($from, $to);

        // Period-over-period baseline: contiguous equal-length window
        // immediately before `from`. Baselines stay per product|name|satuan
        // so renamed/re-priced products still match their order_items rows.
        $windowDays = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $prevTo = Carbon::parse($from)->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($windowDays - 1);
        $prevSales = $this->productSalesRows($prevFrom->toDateString(), $prevTo->toDateString());
        $prevReturns = $this->productReturnRows($prevFrom->toDateString(), $prevTo->toDateString());

        $baseline = [];
        foreach ($prevSales as $key => $row) {
            $ret = $prevReturns->get($key);
            $baseline[$key] = [
                'qty' => (int) $row->total_qty - (int) ($ret->returned_qty ?? 0),
                'revenue' => (float) $row->total_revenue - (float) ($ret->returns_total ?? 0),
            ];
        }

        $items = $sales->map(function ($row) use ($returnAgg, $baseline) {
            $key = $row->product_id.'|'.$row->product_name.'|'.$row->satuan;
            $return = $returnAgg->get($key);
            $returnedQty = (int) ($return->returned_qty ?? 0);
            $returnsTotal = (float) ($return->returns_total ?? 0);

            $netQty = (int) $row->total_qty - $returnedQty;
            $netRevenue = (float) $row->total_revenue - $returnsTotal;
            $prevQty = (int) ($baseline[$key]['qty'] ?? 0);
            $prevRevenue = (float) ($baseline[$key]['revenue'] ?? 0);

            return [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'satuan' => (string) $row->satuan,
                'total_orders' => (int) $row->total_orders,
                'total_revenue' => (float) $row->total_revenue,
                'total_qty' => (int) $row->total_qty,
                'total_cost' => (float) $row->total_cost,
                'net_profit' => (float) $row->total_revenue - (float) $row->total_cost,
                'returned_qty' => $returnedQty,
                'returns_total' => $returnsTotal,
                'net_qty' => $netQty,
                'net_revenue' => $netRevenue,
                'prev_net_qty' => $prevQty,
                'prev_net_revenue' => round($prevRevenue, 2),
                'delta_qty_pct' => $prevQty > 0 ? round((($netQty - $prevQty) / $prevQty) * 100, 1) : null,
                'delta_revenue_pct' => $prevRevenue > 0.0 ? round((($netRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : null,
            ];
        })
            ->sort(fn ($a, $b) => $b['net_qty'] <=> $a['net_qty']
                ?: $b['total_revenue'] <=> $a['total_revenue']
                ?: strcmp($a['product_name'], $b['product_name']))
            ->values()
            ->all();

        $orderCount = Order::whereBetween('order_date', [$from, $to])
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Draft])
            ->count();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'all' => true,
            'items' => $items,
            'total_orders' => $orderCount,
            'total_revenue' => (float) collect($items)->sum('total_revenue'),
            'total_qty' => (int) collect($items)->sum('total_qty'),
            'total_cost' => (float) collect($items)->sum('total_cost'),
            'net_profit' => (float) collect($items)->sum('net_profit'),
            'returned_qty' => (int) collect($items)->sum('returned_qty'),
            'returns_total' => (float) collect($items)->sum('returns_total'),
            'net_qty' => (int) collect($items)->sum('net_qty'),
            'net_revenue' => (float) collect($items)->sum('total_revenue') - (float) collect($items)->sum('returns_total'),
        ];
    }

    private function productSalesRows(string $from, string $to): \Illuminate\Support\Collection
    {
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.order_date', [$from, $to])
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Draft->value])
            ->whereNull('order_items.deleted_at')
            ->whereNull('orders.deleted_at')
            ->when($tenantId, fn ($query) => $query->where('orders.tenant_id', $tenantId))
            ->select(
                'order_items.product_id',
                'order_items.product_name',
                'order_items.satuan',
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('COALESCE(SUM(order_items.subtotal), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_qty'),
                DB::raw('COALESCE(SUM(order_items.quantity * COALESCE(order_items.cost_snapshot, 0)), 0) as total_cost'),
            )
            ->groupBy('order_items.product_id', 'order_items.product_name', 'order_items.satuan')
            ->get()
            ->keyBy(fn ($r) => $r->product_id.'|'.$r->product_name.'|'.$r->satuan);
    }

    private function productReturnRows(string $from, string $to): \Illuminate\Support\Collection
    {
        return $this->refundBaseQuery()
            ->where('returns.created_at', '>=', AppTime::dayStartUtc($from))
            ->where('returns.created_at', '<=', AppTime::dayEndUtc($to))
            ->groupBy('order_items.product_id', 'order_items.product_name', 'order_items.satuan')
            ->selectRaw('order_items.product_id, order_items.product_name, order_items.satuan, COALESCE(SUM(return_items.quantity),0) as returned_qty, '.$this->returnsTotalSelect())
            ->get()
            ->keyBy(fn ($r) => $r->product_id.'|'.$r->product_name.'|'.$r->satuan);
    }

    public function byProduct(int $productId, string $from, string $to): array
    {
        return OrderService::rememberAggregate(
            "products.$productId.$from.$to",
            fn () => $this->computeByProduct($productId, $from, $to),
        );
    }

    private function computeByProduct(int $productId, string $from, string $to): array
    {
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        $stats = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $productId)
            ->whereBetween('orders.order_date', [$from, $to])
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Draft->value])
            ->whereNull('order_items.deleted_at')
            ->whereNull('orders.deleted_at')
            ->when($tenantId, fn ($query) => $query->where('orders.tenant_id', $tenantId))
            ->select(
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('COALESCE(SUM(order_items.subtotal), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_qty'),
                DB::raw('COALESCE(SUM(order_items.quantity * COALESCE(order_items.cost_snapshot, 0)), 0) as total_cost'),
            )
            ->first();

        $totalCost = (float) ($stats->total_cost ?? 0);

        $returnStats = $this->refundBaseQuery()
            ->where('order_items.product_id', $productId)
            ->where('returns.created_at', '>=', AppTime::dayStartUtc($from))
            ->where('returns.created_at', '<=', AppTime::dayEndUtc($to))
            ->selectRaw('COALESCE(SUM(return_items.quantity),0) as returned_qty, '.$this->returnsTotalSelect())
            ->first();

        $returnedQty = (int) ($returnStats->returned_qty ?? 0);
        $returnsTotal = (float) ($returnStats->returns_total ?? 0);

        return [
            'period' => ['from' => $from, 'to' => $to],
            'product_id' => $productId,
            'total_orders' => (int) ($stats->total_orders ?? 0),
            'total_revenue' => (float) ($stats->total_revenue ?? 0),
            'total_qty' => (int) ($stats->total_qty ?? 0),
            'total_cost' => $totalCost,
            'net_profit' => (float) ($stats->total_revenue ?? 0) - $totalCost,
            'returned_qty' => $returnedQty,
            'returns_total' => $returnsTotal,
            'net_qty' => (int) ($stats->total_qty ?? 0) - $returnedQty,
            'net_revenue' => (float) ($stats->total_revenue ?? 0) - $returnsTotal,
        ];
    }

    public function byStatus(?string $from = null, ?string $to = null): array
    {
        return OrderService::rememberAggregate(
            'status.'.($from ?? 'all').'.'.($to ?? 'all'),
            fn () => $this->computeByStatus($from, $to),
        );
    }

    private function computeByStatus(?string $from = null, ?string $to = null): array
    {
        $rows = Order::query()
            ->toBase()
            ->when($from !== null && $to !== null, fn ($query) => $query
                ->whereDate('order_date', '>=', $from)
                ->whereDate('order_date', '<=', $to))
            ->selectRaw('status as status_key')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN grand_total > total_paid THEN 1 ELSE 0 END), 0) as unpaid_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN grand_total > total_paid THEN grand_total - total_paid ELSE 0 END), 0) as unpaid_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status_key');

        $activeStatuses = [
            OrderStatus::Draft,
            OrderStatus::Pending,
            OrderStatus::Diproses,
            OrderStatus::Dikirim,
        ];

        $statuses = [];
        $totals = [
            'orders' => 0,
            'active_orders' => 0,
            'active_value' => 0.0,
            'piutang_count' => 0,
            'piutang_amount' => 0.0,
        ];

        foreach (OrderStatus::cases() as $status) {
            $row = $rows->get($status->value);
            $count = (int) ($row->count ?? 0);
            $total = (float) ($row->total ?? 0);
            $unpaidCount = (int) ($row->unpaid_count ?? 0);
            $unpaidAmount = (float) ($row->unpaid_amount ?? 0);

            $statuses[] = [
                'status' => $status->value,
                'count' => $count,
                'total' => $total,
                'unpaid_count' => $unpaidCount,
                'unpaid_amount' => $unpaidAmount,
            ];

            $totals['orders'] += $count;

            if (in_array($status, $activeStatuses, true)) {
                $totals['active_orders'] += $count;
                $totals['active_value'] += $total;
            }

            if ($status !== OrderStatus::Cancelled) {
                $totals['piutang_count'] += $unpaidCount;
                $totals['piutang_amount'] += $unpaidAmount;
            }
        }

        return [
            'period' => ['from' => $from, 'to' => $to],
            'statuses' => $statuses,
            'totals' => $totals,
        ];
    }

    public function taxReport(int $year): array
    {
        return OrderService::rememberAggregate("tax.$year", function () use ($year) {
            return $this->computeTaxReport($year);
        });
    }

    private function computeTaxReport(int $year): array
    {
        $settings = TenantSettings::for();

        $mode = (string) $settings->get('pajak.pph_mode', PphUkm::MODE_NON_PKP);

        if (! PphUkm::isValidMode($mode)) {
            $mode = PphUkm::MODE_NON_PKP;
        }

        // Explicit tenant scoping (same mechanism as the selects below):
        // TenantScope is a no-op in the single-tenant edition, and the CLI
        // tax report binds "currentTenantId" per tenant while iterating.
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        $fakturStatuses = [
            OrderStatus::Pending->value,
            OrderStatus::Diproses->value,
            OrderStatus::Dikirim->value,
            OrderStatus::Selesai->value,
        ];

        $rows = $this->ordersBaseQuery()
            ->when($tenantId, fn ($query) => $query->where('orders.tenant_id', $tenantId))
            ->whereYear('order_date', $year)
            ->selectRaw('substr(DATE(order_date), 6, 2) as month')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total_omzet')
            ->selectRaw('COALESCE(SUM(ppn_amount), 0) as total_ppn')
            ->selectRaw('COALESCE(SUM(CASE WHEN ppn_amount > 0 THEN grand_total - ppn_amount ELSE 0 END), 0) as total_dpp')
            ->selectRaw('COALESCE(SUM(CASE WHEN ppn_amount > 0 AND status IN (?, ?, ?, ?) THEN 1 ELSE 0 END), 0) as faktur_count', $fakturStatuses)
            ->groupBy(DB::raw('substr(DATE(order_date), 6, 2)'))
            ->get()
            ->keyBy(fn ($row) => (int) $row->month);

        $months = [];
        $totals = [
            'orders' => 0,
            'omzet' => 0.0,
            'dpp' => 0.0,
            'ppn' => 0.0,
            'faktur_count' => 0,
        ];
        $pphTotal = 0.0;
        $priorCumulativeOmzet = 0.0;

        for ($month = 1; $month <= 12; $month++) {
            $row = $rows->get($month);
            $omzet = (float) ($row->total_omzet ?? 0);
            $pph = PphUkm::monthlyTax($omzet, $priorCumulativeOmzet, $mode);
            $priorCumulativeOmzet += $omzet;

            $months[] = [
                'month' => $month,
                'orders' => (int) ($row->total_orders ?? 0),
                'omzet' => $omzet,
                'faktur_count' => (int) ($row->faktur_count ?? 0),
                'dpp' => (float) ($row->total_dpp ?? 0),
                'ppn' => (float) ($row->total_ppn ?? 0),
                'pph' => $pph,
            ];

            $totals['orders'] += (int) ($row->total_orders ?? 0);
            $totals['omzet'] += $omzet;
            $totals['dpp'] += (float) ($row->total_dpp ?? 0);
            $totals['ppn'] += (float) ($row->total_ppn ?? 0);
            $totals['faktur_count'] += (int) ($row->faktur_count ?? 0);
            $pphTotal += $pph;
        }

        return [
            'year' => $year,
            'ppn' => $settings->ppnToArray(),
            'pph' => [
                'mode' => $mode,
                'total' => round($pphTotal, 2),
            ],
            'totals' => $totals,
            'months' => $months,
        ];
    }

    /**
     * Refund base query: aggregates from return_items joined to the original
     * order_items so refund value is derived from the same DATA basis as
     * revenue — the per-unit effective price (order_items.subtotal spread over
     * order_items.quantity), i.e. net of item discount and ex-PPN. Each
     * return_items row is valued as ROUND(order_items.subtotal *
     * return_items.quantity / order_items.quantity, 2) (2dp per row keeps
     * partial returns deterministic) before SUMming; see returnsTotalSelect().
     * PPN convention: gross revenue fields (grand_total) stay PPN-inclusive
     * where they already were, while refunds net against the ex-PPN
     * item-subtotal basis consistently at both report levels (daily/trend and
     * per-product). Draft and Cancelled orders are excluded on both sides so
     * returns never net against revenue that was not counted.
     * returns.refund_amount (legacy pre-discount price_snapshot sums) is
     * ignored by reports — history self-corrects from return_items — and
     * ReturnController writes the same effective basis going forward. Joins
     * orders so soft-delete and tenant filters mirror the revenue side;
     * returns.tenant_id is nullable on legacy rows, so tenant scoping goes
     * through orders.tenant_id.
     */
    private function refundBaseQuery(): \Illuminate\Database\Query\Builder
    {
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        return DB::table('return_items')
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->join('orders', 'orders.id', '=', 'returns.order_id')
            ->join('order_items', 'order_items.id', '=', 'return_items.order_item_id')
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at')
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Draft->value])
            ->when($tenantId, fn ($query) => $query->where('orders.tenant_id', $tenantId));
    }

    private function returnsTotalSelect(): string
    {
        return 'COALESCE(SUM(ROUND(order_items.subtotal * return_items.quantity / order_items.quantity, 2)), 0) as returns_total';
    }

    /**
     * Per-day profit basis for reports: sales turnover (item subtotals) minus
     * purchasing cost (quantity * cost_snapshot), from non-deleted order_items
     * of non-draft, non-cancelled orders. Keyed by Y-m-d.
     */
    private function profitRows(string $from, string $to): \Illuminate\Support\Collection
    {
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('order_items.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Draft->value])
            ->when($tenantId, fn ($query) => $query->where('orders.tenant_id', $tenantId))
            ->whereDate('orders.order_date', '>=', $from)
            ->whereDate('orders.order_date', '<=', $to)
            ->select(
                DB::raw('DATE(orders.order_date) as date'),
                DB::raw('COALESCE(SUM(order_items.subtotal), 0) as sales_turnover'),
                DB::raw('COALESCE(SUM(order_items.quantity * COALESCE(order_items.cost_snapshot, 0)), 0) as purchasing_cost'),
            )
            ->groupBy(DB::raw('DATE(orders.order_date)'))
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());
    }

    /**
     * Per-day expense totals for reports. Keyed by Y-m-d.
     */
    private function expenseRows(string $from, string $to): \Illuminate\Support\Collection
    {
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        return DB::table('expenses')
            ->when($tenantId, fn ($query) => $query->where('expenses.tenant_id', $tenantId))
            ->whereDate('expenses.expense_date', '>=', $from)
            ->whereDate('expenses.expense_date', '<=', $to)
            ->select(
                DB::raw('DATE(expenses.expense_date) as date'),
                DB::raw('COALESCE(SUM(expenses.amount), 0) as expenses_total'),
            )
            ->groupBy(DB::raw('DATE(expenses.expense_date)'))
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());
    }

    private function ordersBaseQuery(): \Illuminate\Database\Query\Builder
    {
        return Order::query()
            ->toBase()
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Draft]);
    }

    /**
     * SQL-aggregate equivalent of the former model-hydration summarize():
     * same keys plus additive piutang (unpaid) and aov totals, same
     * int-0/float semantics for empty vs non-empty ranges, and by_status
     * ordered by each status' first order id.
     */
    private function summarizeRange(array $period, \Closure $applyRange): array
    {
        $totals = $applyRange($this->ordersBaseQuery())
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(ppn_amount), 0) as total_ppn')
            ->selectRaw('COALESCE(SUM(CASE WHEN grand_total > total_paid THEN 1 ELSE 0 END), 0) as unpaid_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN grand_total > total_paid THEN grand_total - total_paid ELSE 0 END), 0) as unpaid_amount')
            ->first();

        $statusRows = $applyRange($this->ordersBaseQuery())
            ->selectRaw('status as status_key')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('MIN(id) as first_order_id')
            ->groupBy('status')
            ->orderBy('first_order_id')
            ->get();

        $hasOrders = (int) $totals->total_orders > 0;

        return [
            'period' => $period,
            'total_orders' => (int) $totals->total_orders,
            'total_revenue' => $hasOrders ? (float) $totals->total_revenue : 0,
            'total_ppn' => $hasOrders ? (float) $totals->total_ppn : 0,
            'unpaid_count' => $hasOrders ? (int) $totals->unpaid_count : 0,
            'unpaid_amount' => $hasOrders ? (float) $totals->unpaid_amount : 0,
            'aov' => $hasOrders ? (float) $totals->total_revenue / (int) $totals->total_orders : 0,
            // Plain array, not a Collection: these payloads go through the
            // aggregate cache, which unserializes with objects disabled
            // (config cache.serializable_classes = false).
            'by_status' => $statusRows
                ->pluck('count', 'status_key')
                ->map(fn ($count) => (int) $count)
                ->all(),
        ];
    }
}
