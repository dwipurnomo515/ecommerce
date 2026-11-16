import type { ReactNode } from 'react';
import { ProductCard } from '@/components/shop/product-card';
import type { ProductCardData } from '@/components/shop/product-card';

/** What the server sends per product (App\Support\ProductCards::toCard). */
export type ProductCardRow = ProductCardData & { id: number };

/**
 * The one product grid of the storefront: 2 columns on phones (photos
 * stay big enough to judge), up to 4 on desktop.
 */
export function ProductGrid({
    products,
    empty,
}: {
    products: ProductCardRow[];
    /** Shown instead of the grid when there are no products. */
    empty: ReactNode;
}) {
    if (products.length === 0) {
        return (
            <div className="grid justify-items-center gap-2 rounded-lg border border-dashed border-border px-6 py-14 text-center">
                {empty}
            </div>
        );
    }

    return (
        <ul className="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
            {products.map((product) => (
                <li key={product.id} className="min-w-0">
                    <ProductCard product={product} />
                </li>
            ))}
        </ul>
    );
}
