import { Head, Link } from '@inertiajs/react';
import { CategoryLinks } from '@/components/shop/category-links';
import type { CategoryLink } from '@/components/shop/category-links';
import { ProductGrid } from '@/components/shop/product-grid';
import type { ProductCardRow } from '@/components/shop/product-grid';
import { buttonVariants } from '@/components/ui/button';
import { search } from '@/routes/shop';
import { create as openStore } from '@/routes/seller/store';

type Props = {
    categories: CategoryLink[];
    products: ProductCardRow[];
};

export default function Home({ categories, products }: Props) {
    return (
        <>
            <Head title="Belanja dari toko lokal">
                <meta
                    head-key="description"
                    name="description"
                    content="Zetago: marketplace untuk belanja langsung dari toko-toko lokal."
                />
            </Head>

            <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-6 md:gap-14 md:px-6 md:py-10">
                {/* No big hero banner: the products are the point. */}
                <section className="grid gap-5">
                    <h1 className="max-w-xl text-2xl leading-tight font-semibold tracking-tight md:text-3xl">
                        Belanja langsung dari toko-toko lokal.
                    </h1>
                    <CategoryLinks categories={categories} label="Kategori" />
                </section>

                <section className="grid gap-5">
                    <div className="flex items-baseline justify-between gap-4">
                        <h2 className="text-lg font-semibold">
                            Produk terbaru
                        </h2>
                        {products.length > 0 && (
                            <Link
                                href={search()}
                                className="text-sm font-medium text-primary underline-offset-2 hover:underline"
                            >
                                Lihat semua
                            </Link>
                        )}
                    </div>
                    <ProductGrid
                        products={products}
                        empty={
                            <>
                                <p className="font-medium">
                                    Belum ada produk yang dijual
                                </p>
                                <p className="max-w-sm text-sm text-muted-foreground">
                                    Zetago baru dibuka. Punya barang untuk
                                    dijual? Jadilah toko pertama.
                                </p>
                                <Link
                                    href={openStore()}
                                    className={buttonVariants({
                                        variant: 'outline',
                                        className: 'mt-2',
                                    })}
                                >
                                    Buka toko
                                </Link>
                            </>
                        }
                    />
                </section>
            </div>
        </>
    );
}
