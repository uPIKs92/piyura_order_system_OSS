import { useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import { flattenProductUnits, formatUnitLabel } from '@/lib/products';
import type { Product } from '@/lib/types';
import { cn } from '@/lib/utils';

interface ProductSearchSelectProps {
    products: Product[];
    value: number | string;
    onChange: (productUnitId: number) => void;
}

export function ProductSearchSelect({ products, value, onChange }: ProductSearchSelectProps) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);

    const options = useMemo(() => flattenProductUnits(products), [products]);
    const selected = options.find((option) => option.id === Number(value));

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return options.slice(0, 20);
        return options
            .filter((option) => formatUnitLabel(option.product!, option).toLowerCase().includes(q))
            .slice(0, 20);
    }, [options, query]);

    return (
        <div className="relative flex-1">
            <Input
                placeholder={selected ? formatUnitLabel(selected.product!, selected) : 'Cari produk...'}
                value={open ? query : (selected ? formatUnitLabel(selected.product!, selected) : query)}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => setTimeout(() => setOpen(false), 150)}
            />
            {open && (
                <div className="absolute z-[110] mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover shadow-md">
                    {filtered.map((option) => (
                        <button
                            key={option.id}
                            type="button"
                            className={cn(
                                'w-full px-3 py-2 text-left text-sm hover:bg-muted',
                                Number(value) === option.id && 'bg-muted',
                            )}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => {
                                onChange(option.id);
                                setQuery('');
                                setOpen(false);
                            }}
                        >
                            {formatUnitLabel(option.product!, option)}
                        </button>
                    ))}
                    {filtered.length === 0 && (
                        <p className="px-3 py-2 text-sm text-muted-foreground">Produk tidak ditemukan</p>
                    )}
                </div>
            )}
        </div>
    );
}
