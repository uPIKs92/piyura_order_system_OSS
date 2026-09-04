import { Bar, CartesianGrid, ComposedChart, Line, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import { formatCurrency } from '@/lib/format';
import type { DailyTrendPoint } from '@/lib/types';

const chartConfig = {
    sales_turnover: {
        label: 'Sales Turnover',
        color: 'var(--chart-1)',
    },
    purchasing_cost: {
        label: 'Purchasing Cost (COGS)',
        color: 'var(--chart-2)',
    },
    net_profit: {
        label: 'Net Profit',
        color: 'var(--chart-3)',
    },
    laba_bersih: {
        label: 'Laba Bersih',
        color: 'var(--chart-4)',
    },
} satisfies ChartConfig;

function formatAxisLabel(date: string): string {
    const [, month, day] = date.split('-');
    return `${Number(day)}/${Number(month)}`;
}

function formatAxisValue(value: number): string {
    if (value >= 1_000_000) {
        return `${(value / 1_000_000).toFixed(1)}jt`;
    }
    if (value >= 1_000) {
        return `${Math.round(value / 1_000)}rb`;
    }
    return String(value);
}

export function ProfitTrendChart({ items }: { items: DailyTrendPoint[] }) {
    const data = items.map((item) => ({
        ...item,
        label: formatAxisLabel(item.date),
    }));

    if (data.length === 0) {
        return <p className="text-sm text-muted-foreground">Tidak ada data pada periode ini</p>;
    }

    return (
        <div className="relative isolate w-full overflow-hidden">
            <ChartContainer config={chartConfig} className="h-64 w-full max-h-64 min-h-64">
                <ComposedChart
                    data={data}
                    margin={{ left: 4, right: 8, top: 8, bottom: 4 }}
                    barGap={4}
                    barCategoryGap="18%"
                >
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="label"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                    interval="preserveStartEnd"
                    minTickGap={24}
                />
                <YAxis
                    tickLine={false}
                    axisLine={false}
                    tickMargin={4}
                    width={44}
                    tickFormatter={formatAxisValue}
                />
                <ChartTooltip
                    content={
                        <ChartTooltipContent
                            formatter={(value, name) => (
                                <span className="font-medium">
                                    {chartConfig[name as keyof typeof chartConfig]?.label}:{' '}
                                    {formatCurrency(Number(value))}
                                </span>
                            )}
                            labelFormatter={(_, payload) => {
                                const row = payload?.[0]?.payload as DailyTrendPoint | undefined;
                                if (!row) return '';
                                return `${row.date} - ${row.total_orders} pesanan`;
                            }}
                        />
                    }
                />
                <ChartLegend content={<ChartLegendContent />} />
                <Bar dataKey="sales_turnover" fill="var(--color-sales_turnover)" radius={4} />
                <Bar dataKey="purchasing_cost" fill="var(--color-purchasing_cost)" radius={4} />
                <Bar dataKey="net_profit" fill="var(--color-net_profit)" radius={4} />
                <Line
                    dataKey="laba_bersih"
                    type="monotone"
                    stroke="var(--color-laba_bersih)"
                    strokeWidth={2}
                    dot={false}
                />
            </ComposedChart>
        </ChartContainer>
        </div>
    );
}
