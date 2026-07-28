import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Skeleton } from '@/components/ui/skeleton';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { IosListRow } from '@/components/ios/IosListRow';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useApi } from '@/lib/ApiProvider';
import { STOCK_MOVEMENT_LABELS } from '@/lib/inventory';
import type { PaginatedResponse, StockMovement } from '@/lib/types';

function formatDelta(delta: number): string {
    return delta > 0 ? `+${delta}` : String(delta);
}

export function StockMovementsPanel({ refreshKey = 0 }: { refreshKey?: number }) {
    const { api } = useApi();
    const [movements, setMovements] = useState<StockMovement[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const data = await api.list<PaginatedResponse<StockMovement>>('inventory/movements', {
                page: String(page),
                per_page: '30',
            });
            setMovements(data.data);
            setLastPage(data.last_page);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat riwayat stok');
        } finally {
            setLoading(false);
        }
    }, [api, page]);

    useEffect(() => {
        load();
    }, [load, refreshKey]);

    if (loading) {
        return (
            <div className="flex flex-col gap-3">
                <Skeleton className="h-16 w-full" />
                <Skeleton className="h-16 w-full" />
            </div>
        );
    }

    if (movements.length === 0) {
        return (
            <Empty>
                <EmptyTitle>Belum ada pergerakan stok</EmptyTitle>
                <EmptyDescription>
                    Gunakan Restok cepat atau Penerimaan supplier di atas untuk menambah stok.
                </EmptyDescription>
            </Empty>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            <GroupedList>
                {movements.map((movement, index) => {
                    const product = movement.product_unit?.product;
                    const label = STOCK_MOVEMENT_LABELS[movement.type] ?? movement.type;
                    const date = new Date(movement.created_at).toLocaleString('id-ID', {
                        day: 'numeric',
                        month: 'short',
                        hour: '2-digit',
                        minute: '2-digit',
                    });

                    return (
                        <div key={movement.id}>
                            {index > 0 && <GroupedListDivider />}
                            <IosListRow
                                title={product?.nama ?? `Unit #${movement.product_unit_id}`}
                                subtitle={`${movement.product_unit?.satuan ?? ''} • ${label} • ${date}`}
                                trailing={
                                    <div className="flex flex-col items-end gap-1">
                                        <Badge variant={movement.quantity_delta < 0 ? 'destructive' : 'secondary'}>
                                            {formatDelta(movement.quantity_delta)}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {movement.quantity_before} → {movement.quantity_after}
                                        </span>
                                    </div>
                                }
                            />
                        </div>
                    );
                })}
            </GroupedList>

            {lastPage > 1 && (
                <div className="flex justify-center gap-2">
                    <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                        Sebelumnya
                    </Button>
                    <span className="flex items-center text-sm text-muted-foreground">
                        {page} / {lastPage}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={page >= lastPage}
                        onClick={() => setPage((p) => p + 1)}
                    >
                        Berikutnya
                    </Button>
                </div>
            )}
        </div>
    );
}
