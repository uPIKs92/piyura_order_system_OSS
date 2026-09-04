import { useEffect, useMemo, useState } from 'react';
import { LayoutGrid, List, Package } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { CategoryFilterButton } from '@/components/catalog/CategoryFilterButton';
import { useApi } from '@/lib/ApiProvider';
import { listAll } from '@/lib/listAll';
import { formatUnitLabel, groupProductUnitsByCategory } from '@/lib/products';
import { stockChipClass } from '@/lib/inventory';
import { formatCurrency, haptic } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Category, Product, ProductUnit } from '@/lib/types';

interface PosProductGridProps {
    products: Product[];
    cartQuantities: Record<number, number>;
    onAdd: (productUnitId: number) => void;
}

interface PosProductItemProps {
    unit: ProductUnit;
    cartQty: number;
    onAdd: (productUnitId: number) => void;
}

function matchesQuery(unit: ProductUnit, normalized: string): boolean {
    if (formatUnitLabel(unit.product!, unit).toLowerCase().includes(normalized)) return true;
    if (unit.product?.sku?.toLowerCase().includes(normalized)) return true;
    return Boolean(unit.product?.nama.toLowerCase().includes(normalized));
}

/** Grid tile for one product unit in the POS picker. */
function PosProductTile({ unit, cartQty, onAdd }: PosProductItemProps) {
    return (
        <button
            type="button"
            className="relative flex w-full flex-col items-start gap-1 rounded-lg border p-2 text-left active:bg-muted/60"
            onClick={() => {
                haptic();
                onAdd(unit.id);
            }}
        >
            {unit.product?.photo_url ? (
                <img
                    src={unit.product.photo_url}
                    alt={unit.product.nama}
                    loading="lazy"
                    className="mb-1 aspect-square w-full rounded-md object-cover"
                />
            ) : (
                <div className="mb-1 flex aspect-square w-full items-center justify-center rounded-md bg-muted">
                    <Package className="size-8 text-muted-foreground/40" />
                </div>
            )}
            <span className="w-full truncate text-sm font-medium">
                {unit.product?.nama ?? 'Produk'}
            </span>
            <span className="flex w-full items-center justify-between gap-1">
                <span className="text-xs text-muted-foreground">{unit.satuan}</span>
                <span className="text-xs font-medium">
                    {formatCurrency(unit.harga_jual)}
                </span>
            </span>
            <span
                className={cn(
                    'rounded px-1.5 py-0.5 text-[10px] font-medium tabular-nums',
                    stockChipClass(unit.stok, unit.min_stok ?? 5),
                )}
            >
                {unit.stok}
            </span>
            {cartQty > 0 && (
                <span className="absolute right-1 top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs font-semibold text-primary-foreground">
                    {cartQty}
                </span>
            )}
        </button>
    );
}

/** List row for one product unit in the POS picker. */
function PosProductRow({ unit, cartQty, onAdd }: PosProductItemProps) {
    return (
        <button
            type="button"
            className="flex w-full items-center gap-3 rounded-lg border bg-card p-2.5 text-left active:bg-muted/60"
            onClick={() => {
                haptic();
                onAdd(unit.id);
            }}
        >
            {unit.product?.photo_url ? (
                <img
                    src={unit.product.photo_url}
                    alt={unit.product.nama}
                    loading="lazy"
                    className="size-14 shrink-0 rounded-md object-cover"
                />
            ) : (
                <div className="flex size-14 shrink-0 items-center justify-center rounded-md bg-muted">
                    <Package className="size-6 text-muted-foreground/40" />
                </div>
            )}
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="truncate text-sm font-medium">
                    {unit.product?.nama ?? 'Produk'}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    {unit.satuan} • {formatCurrency(unit.harga_jual)}
                </span>
                <span
                    className={cn(
                        'w-fit rounded px-1.5 py-0.5 text-[10px] font-medium tabular-nums',
                        stockChipClass(unit.stok, unit.min_stok ?? 5),
                    )}
                >
                    {unit.stok}
                </span>
            </div>
            {cartQty > 0 && (
                <span className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1 text-xs font-semibold text-primary-foreground">
                    {cartQty}
                </span>
            )}
        </button>
    );
}

/** Full-width POS grid: sticky search + category filter, one tile per product unit. */
export function PosProductGrid({ products, cartQuantities, onAdd }: PosProductGridProps) {
    const { api } = useApi();
    const [categories, setCategories] = useState<Category[]>([]);
    const [query, setQuery] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [view, setView] = useState<'grid' | 'list'>(() => {
        const saved = localStorage.getItem('pos:view');
        if (saved === 'grid' || saved === 'list') return saved;
        return typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches
            ? 'list'
            : 'grid';
    });

    useEffect(() => {
        let cancelled = false;
        // Filter chips need every category; endpoint paginates, so walk bounded pages.
        listAll<Category>(api, 'categories')
            .then((items) => {
                if (!cancelled) setCategories(items);
            })
            .catch(() => {
                // grid still usable without category filter
            });
        return () => {
            cancelled = true;
        };
    }, [api]);

    const units = useMemo(() => {
        const filtered = categoryId
            ? products.filter((product) => product.category_id === Number(categoryId))
            : products;
        const groups = groupProductUnitsByCategory(filtered);
        const normalized = query.trim().toLowerCase();
        if (!normalized) return groups.flatMap((group) => group.units);
        return groups
            .flatMap((group) => group.units)
            .filter((unit) => matchesQuery(unit, normalized));
    }, [products, categoryId, query]);

    useEffect(() => {
        localStorage.setItem('pos:view', view);
    }, [view]);

    return (
        <div className="flex flex-col">
            <div className="sticky top-0 z-10 flex gap-2 bg-popover px-4 py-2">
                <Input
                    type="search"
                    inputMode="search"
                    placeholder="Cari produk..."
                    className="flex-1"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
                <CategoryFilterButton
                    categories={categories}
                    value={categoryId}
                    onChange={setCategoryId}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={view === 'grid' ? 'Tampilan daftar' : 'Tampilan kotak'}
                    onClick={() => {
                        haptic();
                        setView(view === 'grid' ? 'list' : 'grid');
                    }}
                >
                    {view === 'grid' ? <LayoutGrid /> : <List />}
                </Button>
            </div>
            {view === 'grid' ? (
                <div className="grid grid-cols-2 gap-2 px-4 pb-2 sm:grid-cols-3">
                    {units.map((unit) => (
                        <PosProductTile
                            key={unit.id}
                            unit={unit}
                            cartQty={cartQuantities[unit.id]}
                            onAdd={onAdd}
                        />
                    ))}
                </div>
            ) : (
                <div className="flex flex-col gap-2 px-4 pb-2">
                    {units.map((unit) => (
                        <PosProductRow
                            key={unit.id}
                            unit={unit}
                            cartQty={cartQuantities[unit.id]}
                            onAdd={onAdd}
                        />
                    ))}
                </div>
            )}
            {units.length === 0 && (
                <Empty className="px-4 py-8">
                    <EmptyTitle>Produk tidak ditemukan</EmptyTitle>
                    <EmptyDescription>Coba ubah kata kunci pencarian atau filter kategori.</EmptyDescription>
                </Empty>
            )}
        </div>
    );
}
