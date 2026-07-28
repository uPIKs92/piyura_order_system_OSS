import { useState } from 'react';
import { PackagePlus, Truck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RestockDrawer } from '@/components/inventory/RestockDrawer';
import { ReceiveStockDrawer } from '@/components/inventory/ReceiveStockDrawer';
import { StockMovementsPanel } from '@/pages/catalog/StockMovementsPanel';

export function StockPanel({ onInventoryChange }: { onInventoryChange?: () => void }) {
    const [restockOpen, setRestockOpen] = useState(false);
    const [receiveOpen, setReceiveOpen] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    function handleSuccess() {
        setRefreshKey((k) => k + 1);
        onInventoryChange?.();
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <Button variant="outline" size="sm" className="w-full shrink-0" onClick={() => setRestockOpen(true)}>
                    <PackagePlus data-icon="inline-start" />
                    Restok cepat
                </Button>
                <Button size="sm" className="w-full shrink-0" onClick={() => setReceiveOpen(true)}>
                    <Truck data-icon="inline-start" />
                    Penerimaan supplier
                </Button>
            </div>

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
