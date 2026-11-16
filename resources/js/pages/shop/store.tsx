import { Head, Link } from '@inertiajs/react';
import { ProductGrid } from '@/components/shop/product-grid';
import type { ProductCardRow } from '@/components/shop/product-grid';
import { SortSelect } from '@/components/shop/sort-select';
import type { Sort } from '@/components/shop/sort-select';
import { joinedText, StoreAvatar } from '@/components/shop/store-avatar';
import { SimplePagination } from '@/components/simple-pagination';
import { home } from '@/routes';
import type { Paginated } from '@/types/pagination';

type Props = {
    store: {
        name: string;
        slug: string;
        description: string | null;
        joined_at: string | null;
    };
    products: Paginated<ProductCardRow>;
    sort: Sort;
};

export default function StorePage({ store, products, sort }: Props) {
    const joined = joinedText(store.joined_at);

    return (
        <>
            <Head title={store.name}>
                {store.description && (
                    <meta
                        head-key="description"
                        name="description"
                        content={store.description.slice(0, 155)}
                    />
                )}
            </Head>

            <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-6 md:px-6 md:py-10">
                <header className="flex items-start gap-4 md:gap-5">
                    <StoreAvatar name={store.name} size="lg" />
                    <div className="grid min-w-0 gap-1.5">
                        <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                            {store.name}
                        </h1>
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {products.total} produk
                            {joined && ` · ${joined}`}
                        </p>
                        {store.description && (
                            <p className="mt-1 max-w-2xl text-sm leading-relaxed whitespace-pre-line text-muted-foreground">
                                {store.description}
                            </p>
                        )}
                    </div>
                </header>

                <section className="grid gap-5">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
                        <h2 className="font-semibold">Produk</h2>
                        {products.total > 1 && <SortSelect value={sort} />}
                    </div>

                    <ProductGrid
                        products={products.data}
                        empty={
                            <>
                                <p className="font-medium">
                                    Toko ini belum punya produk yang dijual
                                </p>
                                <Link
                                    href={home()}
                                    className="text-sm text-primary underline-offset-2 hover:underline"
                                >
                                    Lihat produk lain di Zetago
                                </Link>
                            </>
                        }
                    />

                    <SimplePagination page={products} />
                </section>
            </div>
        </>
    );
}
