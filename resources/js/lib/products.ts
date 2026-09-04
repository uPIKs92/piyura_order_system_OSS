import type { Product, ProductUnit } from '@/lib/types';
import { formatCurrency } from '@/lib/format';

export const UNCATEGORIZED_LABEL = 'Tanpa kategori';

export function flattenProductUnits(products: Product[]): ProductUnit[] {
    return products.flatMap((product) =>
        (product.units ?? []).map((unit) => ({
            ...unit,
            product,
        })),
    );
}

export interface ProductUnitGroup {
    category: string;
    units: ProductUnit[];
}

export function groupProductUnitsByCategory(products: Product[]): ProductUnitGroup[] {
    const groups = new Map<string, ProductUnit[]>();

    for (const product of products) {
        const category = product.category?.nama?.trim() || UNCATEGORIZED_LABEL;
        const units = (product.units ?? []).map((unit) => ({
            ...unit,
            product,
        }));

        if (!units.length) {
            continue;
        }

        const existing = groups.get(category) ?? [];
        groups.set(category, [...existing, ...units]);
    }

    return [...groups.entries()]
        .sort(([left], [right]) => {
            if (left === UNCATEGORIZED_LABEL) return 1;
            if (right === UNCATEGORIZED_LABEL) return -1;
            return left.localeCompare(right, 'id');
        })
        .map(([category, units]) => ({
            category,
            units: units.sort((left, right) =>
                formatUnitLabel(left.product!, left).localeCompare(
                    formatUnitLabel(right.product!, right),
                    'id',
                ),
            ),
        }));
}

export function formatUnitLabel(product: Product, unit: ProductUnit): string {
    return `${product.nama} (${unit.satuan}) — ${formatCurrency(unit.harga_jual)}`;
}
