import { Head } from '@inertiajs/react';
import { CategoryLinks } from '@/components/shop/category-links';
import type { CategoryLink } from '@/components/shop/category-links';
import { ProductGrid } from '@/components/shop/product-grid';
import type { ProductCardRow } from '@/components/shop/product-grid';
import { SortSelect } from '@/components/shop/sort-select';
import type { Sort } from '@/components/shop/sort-select';
import { SimplePagination } from '@/components/simple-pagination';
import type { Paginated } from '@/types/pagination';

type Props = {
    q: string;
    sort: Sort;
    /** null when nothing was typed. */
    products: Paginated<ProductCardRow> | null;
    categories: CategoryLink[];
};

export default function SearchPage({ q, sort, products, categories }: Props) {
    return (
        <>
            {/* Search result pages are thin duplicate content for Google. */}
            <Head title={q ? `Cari "${q}"` : 'Cari produk'}>
                <meta head-key="robots" name="robots" content="noindex" />
            </Head>

            <div className="mx-auto grid w-full max-w-6xl gap-6 px-4 py-6 md:px-6 md:py-10">
                {products === null ? (
                    <div className="grid gap-4">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Semua produk
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Ketik nama produk di kotak pencarian, atau mulai
                            dari kategori.
                        </p>
                        <CategoryLinks
                            categories={categories}
                            label="Kategori"
                        />
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap items-end justify-between gap-3 border-b border-border pb-3">
                            <div className="grid gap-1">
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    Hasil untuk "{q}"
                                </h1>
                                <p className="text-sm text-muted-foreground tabular-nums">
                                    {products.total} produk
                                </p>
                            </div>
                            {products.total > 1 && <SortSelect value={sort} />}
                        </div>

                        <ProductGrid
                            products={products.data}
                            empty={
                                <>
                                    <p className="font-medium">
                                        Tidak ada produk untuk "{q}"
                                    </p>
                                    <p className="max-w-sm text-sm text-muted-foreground">
                                        Coba kata yang lebih umum, atau periksa
                                        ejaannya.
                                    </p>
                                    <div className="mt-3">
                                        <CategoryLinks
                                            categories={categories}
                                            label="Kategori"
                                        />
                                    </div>
                                </>
                            }
                        />

                        <SimplePagination page={products} />
                    </>
                )}
            </div>
        </>
    );
}
