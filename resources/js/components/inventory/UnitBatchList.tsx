import { Badge } from '@/components/ui/badge';
import { daysUntil, expiryChipClass, expiryLabel } from '@/lib/inventory';
import type { ProductBatch } from '@/lib/types';

function batchChipLabel(expiredAt: string | null | undefined, alertDays: number): string {
    const days = daysUntil(expiredAt);
    if (days === null) return 'Aman';
    if (days < 0) return 'Kedaluwarsa';
    if (days <= alertDays) return `${days} hari`;
    return 'Aman';
}

interface UnitBatchListProps {
    batches: ProductBatch[];
    alertDays?: number;
}

export function UnitBatchList({ batches, alertDays = 30 }: UnitBatchListProps) {
    if (batches.length === 0) {
        return <p className="py-1 text-xs text-muted-foreground">Belum ada batch untuk satuan ini</p>;
    }

    return (
        <div className="flex flex-col gap-1">
            {batches.map((batch) => (
                <div key={batch.id} className="flex items-center justify-between gap-3">
                    <div className="flex min-w-0 flex-col gap-0.5">
                        <span className="truncate text-sm font-medium text-foreground">
                            {batch.batch_no?.trim() || 'Tanpa no.'}
                        </span>
                        <span className="text-xs text-muted-foreground">{expiryLabel(batch.expired_at)}</span>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <span className="text-xs text-muted-foreground">× {batch.qty}</span>
                        <Badge className={expiryChipClass(batch.expired_at, alertDays)}>
                            {batchChipLabel(batch.expired_at, alertDays)}
                        </Badge>
                    </div>
                </div>
            ))}
        </div>
    );
}
