import { Area, AreaChart, XAxis, YAxis } from 'recharts';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';

interface KpiCardChartProps {
    data: { date: string; value: number }[];
    color: string;
    gradientId: string;
}

/** Decorative background trend chart for a KPI card: line + gradient area, no axes or tooltip. */
export function KpiCardChart({ data, color, gradientId }: KpiCardChartProps) {
    const config = { value: { color } } satisfies ChartConfig;

    return (
        <div className="pointer-events-none absolute inset-0">
            <ChartContainer config={config} className="h-full w-full">
                <AreaChart data={data} margin={{ top: 6, right: 0, bottom: 6, left: 0 }}>
                    <defs>
                        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={color} stopOpacity={0.2} />
                            <stop offset="100%" stopColor={color} stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <XAxis dataKey="date" hide />
                    <YAxis hide />
                    <Area
                        type="monotone"
                        dataKey="value"
                        stroke="var(--color-value)"
                        strokeWidth={2}
                        fill={`url(#${gradientId})`}
                        dot={false}
                        activeDot={false}
                    />
                </AreaChart>
            </ChartContainer>
        </div>
    );
}
