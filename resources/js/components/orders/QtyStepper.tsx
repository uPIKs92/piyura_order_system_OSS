import { Minus, Plus } from 'lucide-react';

import { cn } from '@/lib/utils';
import { haptic } from '@/lib/format';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface QtyStepperProps {
    value: number;
    onChange: (value: number) => void;
    min?: number;
    max?: number;
    className?: string;
}

export function QtyStepper({
    value,
    onChange,
    min = 1,
    max,
    className,
}: QtyStepperProps) {
    const clamp = (next: number): number => {
        const safe = Number.isFinite(next) ? next : min;
        return Math.max(min, max !== undefined ? Math.min(max, safe) : safe);
    };

    const decrement = () => {
        haptic();
        onChange(clamp(value - 1));
    };

    const increment = () => {
        haptic();
        onChange(clamp(value + 1));
    };

    const handleInput = (e: React.ChangeEvent<HTMLInputElement>) => {
        onChange(clamp(Number(e.target.value)));
    };

    return (
        <div
            className={cn(
                'inline-flex items-center gap-1 rounded-md border border-border bg-background p-1',
                className,
            )}
        >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={decrement}
                disabled={value <= min}
                aria-label="Kurangi jumlah"
            >
                <Minus />
            </Button>
            <Input
                type="number"
                inputMode="numeric"
                value={value}
                min={min}
                max={max}
                onChange={handleInput}
                aria-label="Jumlah"
                className="h-8 w-12 rounded-md border-transparent bg-transparent px-0 text-center text-sm [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={increment}
                disabled={max !== undefined && value >= max}
                aria-label="Tambah jumlah"
            >
                <Plus />
            </Button>
        </div>
    );
}
