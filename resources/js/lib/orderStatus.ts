/**
 * Keep in sync with App\Enums\OrderStatus::allowedTransitions().
 */
export const ORDER_PIPELINE = [
    'draft',
    'pending',
    'diproses',
    'dikirim',
    'selesai',
] as const;

export type OrderPipelineStatus = (typeof ORDER_PIPELINE)[number];
export type OrderStatus = OrderPipelineStatus | 'cancelled';

export const STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    pending: 'Menunggu',
    diproses: 'Diproses',
    dikirim: 'Dikirim',
    selesai: 'Selesai',
    cancelled: 'Batal',
};

const ALLOWED_TRANSITIONS: Record<string, string[]> = {
    draft: ['pending', 'cancelled'],
    pending: ['diproses', 'cancelled'],
    diproses: ['dikirim'],
    dikirim: ['selesai'],
    selesai: [],
    cancelled: [],
};

const ADVANCE_ACTION_LABELS: Record<string, string> = {
    'draft->pending': 'Kirim Pesanan',
    'pending->diproses': 'Mulai Proses',
    'diproses->dikirim': 'Tandai Dikirim',
    'dikirim->selesai': 'Selesaikan',
};

export function getStatusLabel(status: string): string {
    return STATUS_LABELS[status] ?? status;
}

export function getAllowedNextStatuses(status: string): string[] {
    return ALLOWED_TRANSITIONS[status] ?? [];
}

export function getAdvanceStatuses(status: string): string[] {
    return getAllowedNextStatuses(status).filter((next) => next !== 'cancelled');
}

export function canCancel(status: string): boolean {
    return getAllowedNextStatuses(status).includes('cancelled');
}

export function getAdvanceActionLabel(from: string, to: string): string {
    return ADVANCE_ACTION_LABELS[`${from}->${to}`] ?? `Lanjut ke ${getStatusLabel(to)}`;
}

export function isTerminal(status: string): boolean {
    return status === 'selesai' || status === 'cancelled';
}

/**
 * Orders at diproses and beyond (dikirim, selesai) — and cancelled ones —
 * are immutable transaction history: no editing, only status transitions
 * and payments. Keep in sync with OrderService::update()'s lock guard.
 */
export function canEditOrder(status: string): boolean {
    return status === 'draft' || status === 'pending';
}

export function getPipelineIndex(status: string): number {
    if (status === 'cancelled') {
        return -1;
    }

    return ORDER_PIPELINE.indexOf(status as OrderPipelineStatus);
}
