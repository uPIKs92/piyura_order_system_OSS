import { Check, SlidersHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { haptic } from '@/lib/format';
import type { Category } from '@/lib/types';

interface CategoryFilterButtonProps {
    categories: Category[];
    /** Active category id, or '' for "Semua". */
    value: string;
    onChange: (id: string) => void;
}

/**
 * Single-button category filter (Popover dropdown).
 * Replaces the wrapping chip bar — stays compact regardless of category count.
 */
export function CategoryFilterButton({ categories, value, onChange }: CategoryFilterButtonProps) {
    const selected = categories.find((c) => String(c.id) === value);
    const label = value === '' ? 'Semua' : selected?.nama ?? 'Semua';
    const active = value !== '';

    function handleSelect(id: string) {
        haptic();
        onChange(id);
    }

    return (
        <Popover>
            <PopoverTrigger
                render={
                    <Button
                        type="button"
                        variant={active ? 'default' : 'outline'}
                        size="sm"
                        className="shrink-0 gap-1.5"
                    >
                        <SlidersHorizontal data-icon="inline-start" />
                        <span className="max-w-[110px] truncate">{label}</span>
                    </Button>
                }
            />
            <PopoverContent align="end" className="max-h-72 overflow-y-auto p-1">
                <button
                    type="button"
                    className={cn(
                        'flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm',
                        value === '' ? 'bg-muted font-medium text-foreground' : 'text-muted-foreground active:bg-muted',
                    )}
                    onClick={() => handleSelect('')}
                >
                    Semua Kategori
                    {value === '' && <Check className="size-4 shrink-0" />}
                </button>
                {categories.map((cat) => (
                    <button
                        key={cat.id}
                        type="button"
                        className={cn(
                            'flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm',
                            value === String(cat.id)
                                ? 'bg-muted font-medium text-foreground'
                                : 'text-muted-foreground active:bg-muted',
                        )}
                        onClick={() => handleSelect(String(cat.id))}
                    >
                        <span className="truncate">{cat.nama}</span>
                        {value === String(cat.id) && <Check className="size-4 shrink-0" />}
                    </button>
                ))}
            </PopoverContent>
        </Popover>
    );
}
