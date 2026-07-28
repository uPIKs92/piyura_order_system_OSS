import { useState } from 'react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { LowStockSheet } from '@/components/inventory/LowStockSheet';
import { RestockDrawer, type RestockTarget } from '@/components/inventory/RestockDrawer';
import { useInventoryAlerts } from '@/hooks/use-inventory-alerts';
import type { StockAlert } from '@/lib/types';

export function InventoryAlertButton({ compact = false }: { compact?: boolean }) {
    const { alerts, refresh } = useInventoryAlerts();
    const [sheetOpen, setSheetOpen] = useState(false);
    const [restockOpen, setRestockOpen] = useState(false);
    const [restockTarget, setRestockTarget] = useState<RestockTarget | null>(null);

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

    return (
        <>
            <Button
                type="button"
                variant="ghost"
                size={compact ? 'icon-sm' : 'icon'}
                className="relative"
                aria-label="Stok rendah"
                onClick={() => setSheetOpen(true)}
            >
                <Bell />
                {alerts.count > 0 && (
                    <Badge className="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full p-0 text-[10px]">
                        {alerts.count > 99 ? '99+' : alerts.count}
                    </Badge>
                )}
            </Button>

            <LowStockSheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                items={alerts.items}
                onRestock={handleRestock}
            />

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
