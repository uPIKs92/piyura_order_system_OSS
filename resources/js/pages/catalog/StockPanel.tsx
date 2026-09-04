import { useCallback, useEffect, useMemo, useState } from 'react';
import { ChevronDown, PackagePlus, Truck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { IosListRow } from '@/components/ios/IosListRow';
import { StockSummaryCards } from '@/components/catalog/StockSummaryCards';
import { RestockDrawer } from '@/components/inventory/RestockDrawer';
import { ReceiveStockDrawer } from '@/components/inventory/ReceiveStockDrawer';
import { UnitBatchList } from '@/components/inventory/UnitBatchList';
import { StockMovementsPanel } from '@/pages/catalog/StockMovementsPanel';
import { useApi } from '@/lib/ApiProvider';
import { listAll } from '@/lib/listAll';
import { usePullToRefresh, PullToRefreshIndicator } from '@/hooks/use-pull-to-refresh';
import { haptic } from '@/lib/format';
import type { ExpiryAlertsResponse, PaginatedResponse, Product, ProductBatch, StockAlertsResponse } from '@/lib/types';

interface UnitBatchGroup {
    productUnitId: number;
    productName: string;
    satuan: string;
    batches: ProductBatch[];
    totalQty: number;
}

export function StockPanel({ onInventoryChange }: { onInventoryChange?: () => void }) {
    const { api } = useApi();
    const [restockOpen, setRestockOpen] = useState(false);
    const [receiveOpen, setReceiveOpen] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);
    const [totalCount, setTotalCount] = useState(0);
    const [lowCount, setLowCount] = useState(0);
    const [outCount, setOutCount] = useState(0);
    const [batches, setBatches] = useState<ProductBatch[]>([]);
    const [alertDays, setAlertDays] = useState(30);
    const [expandedUnits, setExpandedUnits] = useState<Set<number>>(new Set());

    const loadSummary = useCallback(async () => {
        try {
            const [alerts, productsPage, batchList] = await Promise.all([
                api.list<StockAlertsResponse>('inventory/alerts'),
                // Only the total is needed — a single 1-row page keeps it bounded.
                api.list<PaginatedResponse<Product>>('products', { per_page: '1' }),
                // Batch grouping needs every active batch; endpoint paginates,
                // so walk bounded pages.
                listAll<ProductBatch>(api, 'product-batches'),
            ]);
            setTotalCount(productsPage.total);
            setLowCount(alerts.items.filter((item) => !item.is_out_of_stock).length);
            setOutCount(alerts.items.filter((item) => item.is_out_of_stock).length);
            setBatches(batchList);
        } catch {
            // non-blocking — summary is informational
        }
    }, [api]);

    useEffect(() => {
        void loadSummary();
    }, [loadSummary]);

    useEffect(() => {
        let cancelled = false;
        api.list<ExpiryAlertsResponse>('inventory/expiry-alerts')
            .then((data) => {
                if (!cancelled) setAlertDays(data.alert_days);
            })
            .catch(() => {
                // keep default threshold
            });
        return () => {
            cancelled = true;
        };
    }, [api]);

    const unitBatchGroups = useMemo<UnitBatchGroup[]>(() => {
        const groups = new Map<number, ProductBatch[]>();
        for (const batch of batches) {
            const existing = groups.get(batch.product_unit_id);
            if (existing) {
                existing.push(batch);
            } else {
                groups.set(batch.product_unit_id, [batch]);
            }
        }
        return [...groups.entries()]
            .map(([productUnitId, unitBatches]) => ({
                productUnitId,
                productName: unitBatches[0].product_name,
                satuan: unitBatches[0].satuan,
                totalQty: unitBatches.reduce((sum, batch) => sum + batch.qty, 0),
                batches: unitBatches,
            }))
            .sort(
                (left, right) =>
                    left.productName.localeCompare(right.productName, 'id') ||
                    left.satuan.localeCompare(right.satuan, 'id'),
            );
    }, [batches]);

    const toggleUnit = useCallback((unitId: number) => {
        setExpandedUnits((prev) => {
            const next = new Set(prev);
            if (next.has(unitId)) {
                next.delete(unitId);
            } else {
                next.add(unitId);
            }
            return next;
        });
    }, []);

    function handleSuccess() {
        setRefreshKey((key) => key + 1);
        void loadSummary();
        onInventoryChange?.();
    }

    const { pulling, refreshing, bind } = usePullToRefresh(async () => {
        await loadSummary();
        setRefreshKey((key) => key + 1);
    });

    return (
        <div className="flex flex-col gap-4" {...bind}>
            <PullToRefreshIndicator pulling={pulling} refreshing={refreshing} />

            <StockSummaryCards totalCount={totalCount} lowCount={lowCount} outCount={outCount} />

            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <Button variant="outline" size="sm" className="w-full shrink-0" onClick={() => { haptic(); setRestockOpen(true); }}>
                    <PackagePlus data-icon="inline-start" />
                    Restok cepat
                </Button>
                <Button size="sm" className="w-full shrink-0" onClick={() => { haptic(); setReceiveOpen(true); }}>
                    <Truck data-icon="inline-start" />
                    Penerimaan supplier
                </Button>
            </div>

            <Card size="sm" className="shadow-none">
                <CardHeader>
                    <CardTitle>Batch</CardTitle>
                    <CardAction>
                        <span className="text-xs text-muted-foreground">Batch aktif: {batches.length}</span>
                    </CardAction>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    {unitBatchGroups.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Belum ada batch aktif.</p>
                    ) : (
                        <GroupedList>
                            {unitBatchGroups.map((group, index) => {
                                const open = expandedUnits.has(group.productUnitId);
                                return (
                                    <div key={group.productUnitId}>
                                        {index > 0 && <GroupedListDivider />}
                                        <Collapsible open={open}>
                                            <IosListRow
                                                title={group.productName}
                                                subtitle={`${group.satuan} • Total ${group.totalQty}`}
                                                onClick={() => { haptic(); toggleUnit(group.productUnitId); }}
                                                trailing={
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="secondary">{group.batches.length} batch</Badge>
                                                        <ChevronDown
                                                            className={`size-4 text-muted-foreground transition-transform ${open ? 'rotate-180' : ''}`}
                                                        />
                                                    </div>
                                                }
                                            />
                                            <CollapsibleContent className="border-t bg-muted/30 px-5 py-3 sm:px-6">
                                                <UnitBatchList batches={group.batches} alertDays={alertDays} />
                                            </CollapsibleContent>
                                        </Collapsible>
                                    </div>
                                );
                            })}
                        </GroupedList>
                    )}
                </CardContent>
            </Card>

            <Card size="sm" className="shadow-none">
                <CardHeader>
                    <CardTitle>Riwayat</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    <StockMovementsPanel refreshKey={refreshKey} />
                </CardContent>
            </Card>

            <RestockDrawer
                open={restockOpen}
                onOpenChange={setRestockOpen}
                target={null}
                title="Restok cepat"
                onSuccess={handleSuccess}
            />

            <ReceiveStockDrawer
                open={receiveOpen}
                onOpenChange={setReceiveOpen}
                onSuccess={handleSuccess}
            />
        </div>
    );
}
