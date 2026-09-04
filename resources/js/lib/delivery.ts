import type { DeliveryFeeSettings, DeliveryMethod } from '@/lib/types';

/**
 * Client-side delivery fee preview mirroring the canonical server math
 * (OrderService::resolveDeliveryAttributes + DeliveryEstimateService::feeForKm):
 * diambil -> 0; diantar -> override, else per_km (rate x km clamped by min
 * fee, integer cents half-up) or fixed_fee; per_km without km -> 0.
 */

function toCents(value: number | string | null | undefined): number {
    return Math.round(Number(value ?? 0) * 100);
}

function isEmptyValue(value: string | number | null | undefined): boolean {
    return value === null || value === undefined || String(value).trim() === '';
}

export function resolveDeliveryFee(
    settings: DeliveryFeeSettings | null,
    method: DeliveryMethod | undefined,
    distanceKm: string | number | null | undefined,
    override: string | number | null | undefined,
): number {
    if (method !== 'diantar') return 0;

    if (!isEmptyValue(override)) {
        return Math.max(0, toCents(override)) / 100;
    }

    if (!settings) return 0;

    if (settings.fee_mode === 'fixed') {
        return Math.max(0, toCents(settings.fixed_fee)) / 100;
    }

    if (isEmptyValue(distanceKm)) return 0;

    const kmHundredths = Math.max(0, Math.round(Number(distanceKm) * 100));
    const perKmCents = Math.max(0, toCents(settings.fee_per_km));
    const feeCents = Math.floor((perKmCents * kmHundredths + 50) / 100);

    return Math.max(feeCents, Math.max(0, toCents(settings.min_fee))) / 100;
}

export function deliveryMethodLabel(
    method: DeliveryMethod | undefined,
    distanceKm: number | string | null | undefined,
): string {
    if (method !== 'diantar') return 'Diambil';
    if (isEmptyValue(distanceKm)) return 'Diantar';
    return `Diantar (${Number(distanceKm)} km)`;
}
