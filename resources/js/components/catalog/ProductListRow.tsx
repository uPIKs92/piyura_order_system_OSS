import { Package } from 'lucide-react';
import { useLongPress } from '@/hooks/use-long-press';
import { formatCurrency, haptic } from '@/lib/format';
import { stockChipClass } from '@/lib/inventory';
import { cn } from '@/lib/utils';
import type { Product, ProductUnit } from '@/lib/types';

interface ProductListRowProps {
    product: Product;
    onEdit: (product: Product) => void;
    onRestock: (product: Product, unit: ProductUnit) => void;
}

function defaultUnit(product: Product): ProductUnit | undefined {
    return product.units?.find((u) => u.is_default) ?? product.units?.[0];
}

function unitSummary(product: Product): string {
    const units = product.units ?? [];
    if (!units.length) return '-';
    const minPrice = Math.min(...units.map((unit) => Number(unit.harga_jual)));
    return `dari ${formatCurrency(minPrice)}`;
}

/**
 * Horizontal list-row variant of ProductTile.
 * Same interactions: tap = edit, long-press / right-click = restock.
 */
export function ProductListRow({ product, onEdit, onRestock }: ProductListRowProps) {
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
            className="flex w-full items-center gap-3 rounded-lg border bg-card p-2.5 text-left active:bg-muted/60"
            style={{ touchAction: 'pan-y' }}
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
                    className="size-14 shrink-0 rounded-md object-cover"
                />
            ) : (
                <div className="flex size-14 shrink-0 items-center justify-center rounded-md bg-muted">
                    <Package className="size-6 text-muted-foreground/40" />
                </div>
            )}

            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="truncate text-sm font-medium text-foreground">{product.nama}</span>
                <span className="truncate text-xs text-muted-foreground">
                    {product.category?.nama ? `${product.category.nama} • ` : ''}
                    {unitSummary(product)}
                </span>
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
            </div>
        </button>
    );
}
