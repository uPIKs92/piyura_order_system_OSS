import { ChevronUp, ShoppingBasket } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatCurrency, haptic } from '@/lib/format';

interface CartBarProps {
    count: number;
    total: number;
    onOpen: () => void;
}

/** Drawer-footer bar showing cart summary; hidden when the cart is empty. */
export function CartBar({ count, total, onOpen }: CartBarProps) {
    if (count <= 0) return null;

    return (
        <Button
            type="button"
            aria-label="Lihat keranjang"
            className="h-14 w-full justify-between rounded-xl px-4 active:opacity-90"
            onClick={() => {
                haptic();
                onOpen();
            }}
        >
            <span className="flex items-center gap-2">
                <ShoppingBasket className="size-5" />
                <span className="text-sm font-medium">{count} item</span>
            </span>
            <span className="flex items-center gap-1">
                <span className="text-base font-semibold tabular-nums">
                    {formatCurrency(total)}
                </span>
                <ChevronUp className="size-5 opacity-80" />
            </span>
        </Button>
    );
}
