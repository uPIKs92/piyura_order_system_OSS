import { PackagePlus } from 'lucide-react';
import { ResponsiveFormPanel } from '@/components/ResponsiveFormPanel';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { IosListRow } from '@/components/ios/IosListRow';
import { stockChipClass } from '@/lib/inventory';
import type { StockAlert } from '@/lib/types';

interface LowStockSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    items: StockAlert[];
    onRestock: (item: StockAlert) => void;
}

export function LowStockSheet({ open, onOpenChange, items, onRestock }: LowStockSheetProps) {
    return (
        <ResponsiveFormPanel
            open={open}
            onOpenChange={onOpenChange}
            title="Stok Rendah"
            description={`${items.length} satuan perlu perhatian — stok di bawah batas minimum.`}
            mobileContentClass="max-h-[85vh]"
        >
            {items.length === 0 ? (
                <Empty>
                    <EmptyTitle>Semua stok aman</EmptyTitle>
                    <EmptyDescription>Tidak ada produk di bawah batas minimum.</EmptyDescription>
                </Empty>
            ) : (
                <GroupedList>
                    {items.map((item, index) => (
                        <div key={item.product_unit_id}>
                            {index > 0 && <GroupedListDivider />}
                            <IosListRow
                                title={item.product_name}
                                subtitle={`${item.category_name ? `${item.category_name} • ` : ''}${item.satuan}`}
                                trailing={
                                    <div className="flex items-center gap-2">
                                        <Badge className={stockChipClass(item.stok, item.min_stok)}>
                                            {item.stok}/{item.min_stok}
                                        </Badge>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => onRestock(item)}
                                        >
                                            <PackagePlus data-icon="inline-start" />
                                            Restok
                                        </Button>
                                    </div>
                                }
                            />
                        </div>
                    ))}
                </GroupedList>
            )}
        </ResponsiveFormPanel>
    );
}
