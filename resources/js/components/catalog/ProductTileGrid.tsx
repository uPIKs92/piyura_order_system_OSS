import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { ProductTile } from '@/components/catalog/ProductTile';
import { ProductTileSkeleton } from '@/components/catalog/ProductTileSkeleton';
import type { Product, ProductUnit } from '@/lib/types';

interface ProductTileGridProps {
    products: Product[];
    loading: boolean;
    onEdit: (product: Product) => void;
    onRestock: (product: Product, unit: ProductUnit) => void;
}

export function ProductTileGrid({
    products,
    loading,
    onEdit,
    onRestock,
}: ProductTileGridProps) {
    if (loading) {
        return (
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                {Array.from({ length: 8 }).map((_, i) => (
                    <ProductTileSkeleton key={i} />
                ))}
            </div>
        );
    }

    if (products.length === 0) {
        return (
            <Empty>
                <EmptyTitle>Belum ada produk</EmptyTitle>
                <EmptyDescription>
                    Tidak ada produk yang cocok dengan filter.
                </EmptyDescription>
            </Empty>
        );
    }

    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
            {products.map((product) => (
                <ProductTile
                    key={product.id}
                    product={product}
                    onEdit={onEdit}
                    onRestock={onRestock}
                />
            ))}
        </div>
    );
}
