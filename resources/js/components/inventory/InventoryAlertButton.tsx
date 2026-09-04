import { useState } from 'react';
import { toast } from 'sonner';
import { Bell, CalendarClock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel } from '@/components/ui/field';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { LowStockSheet } from '@/components/inventory/LowStockSheet';
import { ExpirySheet } from '@/components/inventory/ExpirySheet';
import { RestockDrawer, type RestockTarget } from '@/components/inventory/RestockDrawer';
import { useInventoryAlerts } from '@/hooks/use-inventory-alerts';
import { useApi } from '@/lib/ApiProvider';
import { haptic } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ExpiryAlertBatch, ProductBatch, StockAlert } from '@/lib/types';

export function InventoryAlertButton({ compact = false }: { compact?: boolean }) {
    const { api } = useApi();
    const { alerts, expiry, expiredCount, refresh } = useInventoryAlerts();
    const [sheetOpen, setSheetOpen] = useState(false);
    const [restockOpen, setRestockOpen] = useState(false);
    const [restockTarget, setRestockTarget] = useState<RestockTarget | null>(null);
    const [expiryOpen, setExpiryOpen] = useState(false);
    const [writeOffOpen, setWriteOffOpen] = useState(false);
    const [writeOffTarget, setWriteOffTarget] = useState<ExpiryAlertBatch | null>(null);
    const [writeOffReason, setWriteOffReason] = useState('');
    const [writeOffLoading, setWriteOffLoading] = useState(false);

    const totalAlertCount = alerts.count + expiredCount;

    function handleRestock(item: StockAlert) {
        setRestockTarget({
            productName: item.product_name,
            unit: {
                id: item.product_unit_id,
                product_id: item.product_id,
                satuan: item.satuan,
                stok: item.stok,
                min_stok: item.min_stok,
                harga_jual: 0,
                harga_beli: 0,
                is_default: false,
            },
        });
        setSheetOpen(false);
        setRestockOpen(true);
    }

    function handleWriteOff(batch: ExpiryAlertBatch) {
        setWriteOffTarget(batch);
        setWriteOffReason('');
        setWriteOffOpen(true);
    }

    async function confirmWriteOff() {
        if (!writeOffTarget) return;
        setWriteOffLoading(true);
        try {
            const reason = writeOffReason.trim();
            await api.request<ProductBatch>(`/product-batches/${writeOffTarget.id}/write-off`, {
                method: 'POST',
                body: reason ? { reason } : {},
            });
            haptic();
            toast.success('Batch ditulis rusak');
            setWriteOffOpen(false);
            setWriteOffTarget(null);
            await refresh();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menulis rusak batch');
        } finally {
            setWriteOffLoading(false);
        }
    }

    return (
        <>
            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size={compact ? 'icon-sm' : 'icon'}
                    className={cn('relative', expiredCount > 0 && 'text-destructive')}
                    aria-label="Stok rendah"
                    onClick={() => setSheetOpen(true)}
                >
                    <Bell />
                    {totalAlertCount > 0 && (
                        <Badge className="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full p-0 text-[10px]">
                            {totalAlertCount > 99 ? '99+' : totalAlertCount}
                        </Badge>
                    )}
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    size={compact ? 'icon-sm' : 'icon'}
                    className={cn('relative', expiredCount > 0 && 'text-destructive')}
                    aria-label="Kedaluwarsa"
                    onClick={() => setExpiryOpen(true)}
                >
                    <CalendarClock />
                    {expiredCount > 0 && (
                        <Badge className="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full p-0 text-[10px] bg-destructive text-destructive-foreground">
                            {expiredCount > 99 ? '99+' : expiredCount}
                        </Badge>
                    )}
                </Button>
            </div>

            <LowStockSheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                items={alerts.items}
                onRestock={handleRestock}
            />

            <ExpirySheet
                open={expiryOpen}
                onOpenChange={setExpiryOpen}
                data={expiry}
                onWriteOff={handleWriteOff}
            />

            <AlertDialog open={writeOffOpen} onOpenChange={setWriteOffOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Tulis rusak batch ini?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Qty {writeOffTarget?.product_name ?? 'batch'} akan dinolkan dan pergerakan stok
                            write-off dicatat. Tindakan ini tidak bisa dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <Field>
                        <FieldLabel htmlFor="write-off-reason">Alasan (opsional)</FieldLabel>
                        <Input
                            id="write-off-reason"
                            value={writeOffReason}
                            onChange={(e) => setWriteOffReason(e.target.value)}
                            placeholder="Rusak, kedaluwarsa, dll."
                        />
                    </Field>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={writeOffLoading}>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground"
                            disabled={writeOffLoading}
                            onClick={() => void confirmWriteOff()}
                        >
                            Write-off
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <RestockDrawer
                open={restockOpen}
                onOpenChange={setRestockOpen}
                target={restockTarget}
                showBarcode={false}
                showProductSearch={false}
                onSuccess={refresh}
            />
        </>
    );
}
