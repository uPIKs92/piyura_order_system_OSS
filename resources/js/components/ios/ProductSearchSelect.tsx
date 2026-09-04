import { useMemo, useState, type KeyboardEvent } from 'react';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    formatUnitLabel,
    groupProductUnitsByCategory,
    type ProductUnitGroup,
} from '@/lib/products';
import type { Product, ProductUnit } from '@/lib/types';
import { cn } from '@/lib/utils';

interface ProductSearchSelectProps {
    products: Product[];
    value: number | string;
    onChange: (productUnitId: number) => void;
}

const MAX_RESULTS = 40;

function filterGroups(groups: ProductUnitGroup[], query: string): ProductUnitGroup[] {
    const normalized = query.trim().toLowerCase();
    if (!normalized) {
        return groups
            .map((group) => ({
                ...group,
                units: group.units.slice(0, MAX_RESULTS),
            }))
            .filter((group) => group.units.length > 0)
            .slice(0, 8);
    }

    return groups
        .map((group) => ({
            ...group,
            units: group.units.filter((unit) =>
                formatUnitLabel(unit.product!, unit).toLowerCase().includes(normalized),
            ),
        }))
        .filter((group) => group.units.length > 0)
        .map((group) => ({
            ...group,
            units: group.units.slice(0, MAX_RESULTS),
        }));
}

function findSelectedUnit(groups: ProductUnitGroup[], value: number | string): ProductUnit | undefined {
    const id = Number(value);
    if (!id) return undefined;

    for (const group of groups) {
        const match = group.units.find((unit) => unit.id === id);
        if (match) return match;
    }

    return undefined;
}

export function ProductSearchSelect({ products, value, onChange }: ProductSearchSelectProps) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);

    const groups = useMemo(() => groupProductUnitsByCategory(products), [products]);
    const selected = findSelectedUnit(groups, value);
    const filteredGroups = useMemo(() => filterGroups(groups, query), [groups, query]);
    const resultCount = filteredGroups.reduce((count, group) => count + group.units.length, 0);

    return (
        <div className="flex-1">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger
                    nativeButton={false}
                    render={
                        <Input
                            placeholder={selected ? formatUnitLabel(selected.product!, selected) : 'Cari produk...'}
                            value={open ? query : (selected ? formatUnitLabel(selected.product!, selected) : query)}
                            onChange={(e) => {
                                setQuery(e.target.value);
                                setOpen(true);
                            }}
                            onKeyDown={(event) => {
                                (event as KeyboardEvent<HTMLInputElement> & { preventBaseUIHandler?: () => void }).preventBaseUIHandler?.();
                            }}
                        />
                    }
                />
                <PopoverContent align="start" initialFocus={false} className="max-h-72 overflow-y-auto p-1">
                    {filteredGroups.map((group) => (
                        <div key={group.category}>
                            <p className="sticky top-0 z-[1] border-b bg-muted/80 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground backdrop-blur-sm">
                                {group.category}
                            </p>
                            {group.units.map((option) => (
                                <button
                                    key={option.id}
                                    type="button"
                                    className={cn(
                                        'w-full px-3 py-2 text-left text-sm hover:bg-muted',
                                        Number(value) === option.id && 'bg-muted',
                                    )}
                                    onClick={() => {
                                        onChange(option.id);
                                        setQuery('');
                                        setOpen(false);
                                    }}
                                >
                                    {formatUnitLabel(option.product!, option)}
                                </button>
                            ))}
                        </div>
                    ))}
                    {resultCount === 0 && (
                        <p className="px-3 py-2 text-sm text-muted-foreground">Produk tidak ditemukan</p>
                    )}
                </PopoverContent>
            </Popover>
        </div>
    );
}
