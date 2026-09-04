import { lazy, Suspense } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { formatCurrency } from '@/lib/format';
import type { OrderSummary } from '@/lib/types';

const KpiCardChart = lazy(() => import('@/components/orders/KpiCardChart').then((m) => ({ default: m.KpiCardChart })));

interface OrderKpiStripProps {
    summary: OrderSummary;
}

/** Three compact KPI cards for the desktop order list: totals, revenue, unpaid. */
export function OrderKpiStrip({ summary }: OrderKpiStripProps) {
    const daily = summary.daily ?? [];
    const revenueSeries = daily.map((point) => ({ date: point.date, value: point.revenue }));
    const orderSeries = daily.map((point) => ({ date: point.date, value: point.orders }));
    const showRevenueChart = daily.some((point) => point.revenue > 0);
    const showOrdersChart = daily.some((point) => point.orders > 0);

    return (
        <div className="grid gap-3 sm:grid-cols-3">
            <Card size="sm" className="relative min-h-24 shadow-none">
                {showOrdersChart && (
                    <Suspense fallback={<div className="pointer-events-none absolute inset-0" aria-hidden />}>
                        <KpiCardChart data={orderSeries} color="var(--chart-2)" gradientId="kpi-total-pesanan-area" />
                    </Suspense>
                )}
                <CardContent className="relative z-10 flex flex-col items-start gap-1">
                    <span className="text-lg font-semibold tabular-nums">{summary.total_orders}</span>
                    <span className="text-xs text-muted-foreground">Total Pesanan</span>
                </CardContent>
            </Card>
            <Card size="sm" className="relative min-h-24 shadow-none">
                {showRevenueChart && (
                    <Suspense fallback={<div className="pointer-events-none absolute inset-0" aria-hidden />}>
                        <KpiCardChart data={revenueSeries} color="var(--chart-1)" gradientId="kpi-omzet-area" />
                    </Suspense>
                )}
                <CardContent className="relative z-10 flex flex-col items-start gap-1">
                    <span className="text-lg font-semibold tabular-nums">{formatCurrency(summary.total_revenue)}</span>
                    <span className="text-xs text-muted-foreground">Omzet</span>
                </CardContent>
            </Card>
            <Card size="sm" className="shadow-none ring-destructive/20">
                <CardContent className="flex flex-col items-start gap-1">
                    <span className="text-lg font-semibold tabular-nums text-destructive">
                        {formatCurrency(summary.unpaid_amount)}
                    </span>
                    <span className="text-xs text-muted-foreground">Belum Dibayar</span>
                    {summary.unpaid_count > 0 && (
                        <span className="text-xs text-muted-foreground">{summary.unpaid_count} pesanan</span>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
