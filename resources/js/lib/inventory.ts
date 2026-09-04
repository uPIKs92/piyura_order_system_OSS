import type { StockMovementType } from '@/lib/types';

export const STOCK_MOVEMENT_LABELS: Record<StockMovementType, string> = {
    sale: 'Penjualan',
    return: 'Retur',
    restock: 'Restok',
    receive: 'Penerimaan supplier',
    adjustment: 'Penyesuaian',
    cancel_restore: 'Batal pesanan',
    delete_restore: 'Hapus pesanan',
    write_off: 'Write-off',
};

export function isLowStock(stok: number, minStok = 5): boolean {
    return stok <= minStok;
}

export function stockChipClass(stok: number, minStok = 5): string {
    if (stok === 0) return 'bg-destructive/15 text-destructive';
    if (isLowStock(stok, minStok)) return 'bg-warning/15 text-warning';
    return 'bg-success/15 text-success';
}

export function daysUntil(date: string | null | undefined): number | null {
    if (!date) return null;
    const today = new Date();
    const todayMidnight = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const target = new Date(`${date}T00:00:00`);
    return Math.floor((target.getTime() - todayMidnight.getTime()) / 86400000);
}

export function expiryChipClass(expiredAt: string | null | undefined, alertDays = 30): string {
    const days = daysUntil(expiredAt);
    if (days === null) return 'bg-secondary/15 text-secondary-foreground';
    if (days < 0) return 'bg-destructive/15 text-destructive';
    if (days <= alertDays) return 'bg-warning/15 text-warning';
    return 'bg-success/15 text-success';
}

export function expiryLabel(expiredAt: string | null | undefined): string {
    if (!expiredAt) return 'Tanpa kedaluwarsa';
    return new Date(expiredAt).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}
