import { Package } from 'lucide-react';
import { useLongPress } from '@/hooks/use-long-press';
import { formatCurrency, haptic } from '@/lib/format';
import { stockChipClass } from '@/lib/inventory';
import { cn } from '@/lib/utils';
import type { Product, ProductUnit } from '@/lib/types';

interface ProductTileProps {
    product: Product;
    onEdit: (product: Product) => void;
    onRestock: (product: Product, unit: ProductUnit) => void;
}

function defaultUnit(product: Product): ProductUnit | undefined {
    return product.units?.find((u) => u.is_default) ?? product.units?.[0];
}

/** POS-style product tile. Tap = edit, long-press / right-click = restock. */
export function ProductTile({ product, onEdit, onRestock }: ProductTileProps) {
    const units = product.units ?? [];

    function restock() {
        const unit = defaultUnit(product);
        if (unit) onRestock(product, unit);
    }

    const bind = useLongPress(() => {
        haptic();
        restock();
    });

    return (
        <button
            type="button"
            className="relative flex w-full flex-col items-start gap-1 rounded-lg border p-2 text-left active:bg-muted/60"
            onClick={() => {
                haptic();
                onEdit(product);
            }}
            onContextMenu={(e) => {
                e.preventDefault();
                haptic();
                restock();
            }}
            {...bind}
        >
            {product.photo_url ? (
                <img
                    src={product.photo_url}
                    alt={product.nama}
                    loading="lazy"
                    className="mb-1 aspect-square w-full rounded-md object-cover"
                />
            ) : (
                <div className="mb-1 flex aspect-square w-full items-center justify-center rounded-md bg-muted">
                    <Package className="size-8 text-muted-foreground/40" />
                </div>
            )}
            <span className="w-full truncate text-sm font-medium">{product.nama}</span>

            {units.slice(0, 2).map((unit) => (
                <div key={unit.id} className="flex w-full items-center justify-between gap-1">
                    <span className="text-xs text-muted-foreground">{unit.satuan}</span>
                    <span className="text-xs">{formatCurrency(unit.harga_jual)}</span>
                </div>
            ))}

            <div className="mt-0.5 flex flex-wrap gap-1">
                {units.map((unit) => (
                    <span
                        key={unit.id}
                        className={cn(
                            'rounded px-1.5 py-0.5 text-[10px] font-medium tabular-nums',
                            stockChipClass(unit.stok, unit.min_stok ?? 5),
                        )}
                    >
                        {unit.satuan}: {unit.stok}
                    </span>
                ))}
            </div>
        </button>
    );
}
