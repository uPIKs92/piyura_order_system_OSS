import type { StockMovementType } from '@/lib/types';

export const STOCK_MOVEMENT_LABELS: Record<StockMovementType, string> = {
    sale: 'Penjualan',
    return: 'Retur',
    restock: 'Restok',
    receive: 'Penerimaan supplier',
    adjustment: 'Penyesuaian',
    cancel_restore: 'Batal pesanan',
};

export function isLowStock(stok: number, minStok = 5): boolean {
    return stok <= minStok;
}

export function stockChipClass(stok: number, minStok = 5): string {
    if (stok === 0) return 'bg-destructive/15 text-destructive';
    if (isLowStock(stok, minStok)) return 'bg-amber-500/15 text-amber-700 dark:text-amber-400';
    return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400';
}
