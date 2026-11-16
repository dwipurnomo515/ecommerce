import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { CategoryLinks } from '@/components/shop/category-links';
import type { CategoryLink } from '@/components/shop/category-links';
import { ProductGrid } from '@/components/shop/product-grid';
import type { ProductCardRow } from '@/components/shop/product-grid';
import { SortSelect } from '@/components/shop/sort-select';
import type { Sort } from '@/components/shop/sort-select';
import { SimplePagination } from '@/components/simple-pagination';
import { home } from '@/routes';
import { show } from '@/routes/shop/categories';
import type { Paginated } from '@/types/pagination';

type Props = {
    category: { name: string; slug: string; description: string | null };
    parent: CategoryLink | null;
    subcategories: CategoryLink[];
    products: Paginated<ProductCardRow>;
    sort: Sort;
};

export default function CategoryPage({
    category,
    parent,
    subcategories,
    products,
    sort,
}: Props) {
    return (
        <>
            <Head title={category.name}>
                {category.description && (
                    <meta
                        head-key="description"
                        name="description"
                        content={category.description}
                    />
                )}
            </Head>

            <div className="mx-auto grid w-full max-w-6xl gap-6 px-4 py-6 md:px-6 md:py-10">
                <div className="grid gap-2">
                    <Link
                        href={parent ? show(parent.slug) : home()}
                        className="inline-flex w-fit items-center gap-0.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ChevronLeft className="size-4" aria-hidden />
                        {parent ? parent.name : 'Beranda'}
                    </Link>
                    <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                        {category.name}
                    </h1>
                    {category.description && (
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            {category.description}
                        </p>
                    )}
                </div>

                <CategoryLinks
                    categories={subcategories}
                    label={`Subkategori ${category.name}`}
                />

                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {products.total} produk
                    </p>
                    {products.total > 1 && <SortSelect value={sort} />}
                </div>

                <ProductGrid
                    products={products.data}
                    empty={
                        <>
                            <p className="font-medium">
                                Belum ada produk di {category.name}
                            </p>
                            <Link
                                href={home()}
                                className="text-sm text-primary underline-offset-2 hover:underline"
                            >
                                Lihat kategori lain
                            </Link>
                        </>
                    }
                />

                <SimplePagination page={products} />
            </div>
        </>
    );
}
