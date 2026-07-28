import type { OrderFormData } from '@/lib/types';

export function isMeaningfulDraft(data: OrderFormData): boolean {
    if (data.customer_name?.trim()) return true;
    if (data.customer_phone?.trim()) return true;
    if (data.notes?.trim()) return true;

    return data.items?.some((item) => Boolean(item.product_unit_id)) ?? false;
}
