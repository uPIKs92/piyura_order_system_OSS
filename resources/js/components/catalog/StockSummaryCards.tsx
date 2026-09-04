import { AlertTriangle, Package, PackageX } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface StockSummaryCardsProps {
    totalCount: number;
    lowCount: number;
    outCount: number;
}

/** Three compact stat cards: Total SKU, Stok Rendah, Habis. */
export function StockSummaryCards({ totalCount, lowCount, outCount }: StockSummaryCardsProps) {
    const cards = [
        { icon: Package, label: 'Total SKU', value: totalCount, tone: 'text-muted-foreground' },
        {
            icon: AlertTriangle,
            label: 'Stok Rendah',
            value: lowCount,
            tone: 'text-warning',
        },
        { icon: PackageX, label: 'Habis', value: outCount, tone: 'text-destructive' },
    ];

    return (
        <div className="grid grid-cols-3 gap-2">
            {cards.map((card) => {
                const Icon = card.icon;
                return (
                    <Card key={card.label} size="sm" className="shadow-none">
                        <CardContent className="flex flex-col items-start gap-1">
                            <Icon className={cn('size-4', card.tone)} />
                            <span className="text-xl font-semibold tabular-nums">
                                {card.value}
                            </span>
                            <span className="text-xs text-muted-foreground">{card.label}</span>
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
