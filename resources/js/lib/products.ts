import type { Product, ProductUnit } from '@/lib/types';
import { formatCurrency } from '@/lib/format';

export function flattenProductUnits(products: Product[]): ProductUnit[] {
    return products.flatMap((product) =>
        (product.units ?? []).map((unit) => ({
            ...unit,
            product,
        })),
    );
}

export function formatUnitLabel(product: Product, unit: ProductUnit): string {
    return `${product.nama} (${unit.satuan}) — ${formatCurrency(unit.harga_jual)}`;
}
