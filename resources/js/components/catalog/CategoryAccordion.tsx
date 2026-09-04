import { useState } from 'react';
import { Check, ChevronDown, Package, Pencil, Plus, X } from 'lucide-react';
import { useLongPress } from '@/hooks/use-long-press';
import { formatCurrency, haptic } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Product } from '@/lib/types';

interface CategoryAccordionProps {
    title: string;
    products: Product[];
    /** Long-press / right-click on the header opens category edit. Omit for static sections. */
    onEditCategory?: () => void;
    /** Shows the header X button. Omit for static sections (e.g. "Tanpa kategori"). */
    onDeleteCategory?: () => void;
    /** Shows the "Tambah Produk" button. Omit when adding is not meaningful. */
    onAddProduct?: () => void;
    /**
     * X on a product row — toggles the product into the staged-removal queue
     * (non-destructive; applied on confirm).
     */
    onStageRemove?: (product: Product) => void;
    /** Row tap — used to pick a category for uncategorized products. */
    onProductPress?: (product: Product) => void;
    /** Product ids staged for removal — rows render dimmed with a checkmark. */
    stagedProductIds?: number[];
    defaultExpanded?: boolean;
}

function productPrice(product: Product): string {
    const units = product.units ?? [];
    if (!units.length) return '-';
    const min = Math.min(...units.map((u) => Number(u.harga_jual)));
    return `dari ${formatCurrency(min)}`;
}

/**
 * Collapsible category section that holds its product list.
 *
 * Header interactions:
 *  - Tap = expand / collapse
 *  - Pencil button = edit category
 *  - Long-press / right-click = edit category (shortcut)
 *  - X button = delete category
 *
 * Expanded section shows products. Tapping X on a row stages the product for
 * removal (row dims, X becomes a checkmark); the queued removals are applied
 * via the panel's confirm bar. Tapping a staged row's checkmark unstages it.
 */
export function CategoryAccordion({
    title,
    products,
    onEditCategory,
    onDeleteCategory,
    onAddProduct,
    onStageRemove,
    onProductPress,
    stagedProductIds = [],
    defaultExpanded = false,
}: CategoryAccordionProps) {
    const [expanded, setExpanded] = useState(defaultExpanded);

    const bind = useLongPress(() => {
        if (!onEditCategory) return;
        haptic();
        onEditCategory();
    });

    return (
        <div className="overflow-hidden rounded-lg border bg-card">
            {/* Header bar — tap to toggle, long-press / right-click to edit */}
            <div
                className="flex items-center gap-2 p-2.5 active:bg-muted/60"
                onClick={() => {
                    haptic();
                    setExpanded((v) => !v);
                }}
                onContextMenu={(e) => {
                    e.preventDefault();
                    if (!onEditCategory) return;
                    haptic();
                    onEditCategory();
                }}
                role="button"
                tabIndex={0}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        setExpanded((v) => !v);
                    }
                }}
                {...bind}
            >
                <ChevronDown
                    className={cn(
                        'size-4 shrink-0 text-muted-foreground transition-transform',
                        expanded ? '' : '-rotate-90',
                    )}
                />
                <span className="flex-1 truncate text-sm font-medium">{title}</span>
                <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-medium text-primary-foreground tabular-nums">
                    {products.length}
                </span>
                {onEditCategory && (
                    <button
                        type="button"
                        className="shrink-0 rounded p-1 text-muted-foreground/50 active:bg-muted active:text-foreground"
                        aria-label="Ubah kategori"
                        onClick={(e) => {
                            e.stopPropagation();
                            haptic();
                            onEditCategory();
                        }}
                    >
                        <Pencil className="size-4" />
                    </button>
                )}
                {onDeleteCategory && (
                    <button
                        type="button"
                        className="shrink-0 rounded p-1 text-muted-foreground/50 active:bg-destructive/10 active:text-destructive"
                        aria-label="Hapus kategori"
                        onClick={(e) => {
                            e.stopPropagation();
                            haptic();
                            onDeleteCategory();
                        }}
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>

            {/* Expanded product list */}
            {expanded && (
                <div className="flex flex-col gap-1 border-t px-2 py-2">
                    {products.length === 0 ? (
                        <p className="py-3 text-center text-xs text-muted-foreground">
                            Belum ada produk
                        </p>
                    ) : (
                        products.map((product) => {
                            const staged = stagedProductIds.includes(product.id);
                            return (
                                <div
                                    key={product.id}
                                    className={cn(
                                        'flex items-center gap-2 rounded-md p-1.5',
                                        onProductPress && 'active:bg-muted/60',
                                        staged && 'opacity-50',
                                    )}
                                    onClick={
                                        onProductPress
                                            ? () => {
                                                  haptic();
                                                  onProductPress(product);
                                              }
                                            : undefined
                                    }
                                    role={onProductPress ? 'button' : undefined}
                                    tabIndex={onProductPress ? 0 : undefined}
                                    onKeyDown={
                                        onProductPress
                                            ? (e) => {
                                                  if (e.key === 'Enter' || e.key === ' ') {
                                                      e.preventDefault();
                                                      haptic();
                                                      onProductPress(product);
                                                  }
                                              }
                                            : undefined
                                    }
                                >
                                    {product.photo_url ? (
                                        <img
                                            src={product.photo_url}
                                            alt={product.nama}
                                            loading="lazy"
                                            className="size-9 shrink-0 rounded-md object-cover"
                                        />
                                    ) : (
                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted">
                                            <Package className="size-4 text-muted-foreground/40" />
                                        </div>
                                    )}
                                    <div className="flex min-w-0 flex-1 flex-col">
                                        <span className="truncate text-xs font-medium">{product.nama}</span>
                                        <span className="truncate text-[11px] text-muted-foreground">
                                            {productPrice(product)}
                                        </span>
                                    </div>
                                    {onStageRemove && (
                                        <button
                                            type="button"
                                            className={cn(
                                                'shrink-0 rounded p-1 text-muted-foreground/50 active:bg-destructive/10 active:text-destructive',
                                                staged && 'text-primary',
                                            )}
                                            aria-label={
                                                staged
                                                    ? `Batalkan mengeluarkan ${product.nama}`
                                                    : `Keluarkan ${product.nama} dari kategori`
                                            }
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                haptic();
                                                onStageRemove(product);
                                            }}
                                        >
                                            {staged ? <Check className="size-4" /> : <X className="size-4" />}
                                        </button>
                                    )}
                                </div>
                            );
                        })
                    )}

                    {onAddProduct && (
                        <button
                            type="button"
                            className="mt-1 flex items-center justify-center gap-1.5 rounded-md border border-dashed py-2 text-xs font-medium text-muted-foreground active:bg-muted/60"
                            onClick={(e) => {
                                e.stopPropagation();
                                haptic();
                                onAddProduct();
                            }}
                        >
                            <Plus className="size-3.5" />
                            Tambah Produk
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
