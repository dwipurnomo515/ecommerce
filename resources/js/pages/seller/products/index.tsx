import { Head, Link } from '@inertiajs/react';
import { Clock, ImagePlus, Plus } from 'lucide-react';
import ProductController from '@/actions/App/Http/Controllers/Seller/ProductController';
import ProductImageController from '@/actions/App/Http/Controllers/Seller/ProductImageController';
import Heading from '@/components/heading';
import { SimplePagination } from '@/components/simple-pagination';
import { buttonVariants } from '@/components/ui/button';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import { create, index } from '@/routes/seller/products';
import type { Paginated } from '@/types/pagination';

type SellerProduct = {
    id: number;
    name: string;
    cover_url: string | null;
    category: string;
    status: 'draft' | 'active' | 'archived' | 'banned';
    status_label: string;
    variants_count: number;
    min_price: number;
    max_price: number;
    total_stock: number;
};

type Props = {
    products: Paginated<SellerProduct>;
    canCreate: boolean;
};

/** Status shown as text with a small dot: colour only where it means something. */
const statusDot: Record<SellerProduct['status'], string> = {
    active: 'bg-success',
    draft: 'bg-muted-foreground',
    archived: 'bg-muted-foreground',
    banned: 'bg-destructive',
};

function priceRange(p: SellerProduct): string {
    return p.min_price === p.max_price
        ? formatRupiah(p.min_price)
        : `${formatRupiah(p.min_price)} – ${formatRupiah(p.max_price)}`;
}

export default function ProductsIndex({ products, canCreate }: Props) {
    return (
        <>
            <Head title="Produk" />

            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Produk"
                        description={`${products.total} produk di toko Anda`}
                    />
                    {canCreate && (
                        <Link href={create()} className={buttonVariants()}>
                            <Plus />
                            Tambah produk
                        </Link>
                    )}
                </div>

                {!canCreate && (
                    <div
                        role="status"
                        className="flex gap-3 rounded-lg border border-warning/30 p-4"
                    >
                        <Clock
                            className="mt-0.5 size-5 shrink-0 text-warning"
                            aria-hidden
                        />
                        <p className="text-sm text-muted-foreground">
                            Anda bisa menambahkan produk setelah toko disetujui
                            admin.
                        </p>
                    </div>
                )}

                {products.data.length === 0 ? (
                    canCreate && (
                        <div className="grid justify-items-center gap-3 rounded-lg border border-dashed border-border px-6 py-14 text-center">
                            <p className="font-medium">Belum ada produk</p>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                Tambahkan produk pertama Anda. Anda bisa
                                menyimpannya sebagai draf dulu sebelum
                                diterbitkan.
                            </p>
                            <Link
                                href={create()}
                                className={buttonVariants({
                                    variant: 'outline',
                                })}
                            >
                                <Plus />
                                Tambah produk pertama
                            </Link>
                        </div>
                    )
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border bg-card">
                        {products.data.map((p) => (
                            <li
                                key={p.id}
                                className="grid grid-cols-[3.5rem_1fr] gap-x-4 gap-y-2 p-4 sm:grid-cols-[3.5rem_1fr_auto] sm:items-center"
                            >
                                <div className="row-span-2 size-14 overflow-hidden rounded-md bg-muted sm:row-span-1">
                                    {p.cover_url ? (
                                        <img
                                            src={p.cover_url}
                                            alt=""
                                            loading="lazy"
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <span className="grid size-full place-items-center text-muted-foreground">
                                            <ImagePlus
                                                className="size-5"
                                                aria-hidden
                                            />
                                        </span>
                                    )}
                                </div>
                                <div className="grid min-w-0 gap-1">
                                    {/* A banned product is locked: no link to a page that would 403. */}
                                    {p.status === 'banned' ? (
                                        <p className="truncate font-medium">
                                            {p.name}
                                        </p>
                                    ) : (
                                        <Link
                                            href={ProductController.edit(p.id)}
                                            className="truncate font-medium underline-offset-2 hover:underline"
                                        >
                                            {p.name}
                                        </Link>
                                    )}
                                    <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                        <span className="inline-flex items-center gap-1.5 text-foreground">
                                            <span
                                                className={cn(
                                                    'size-1.5 rounded-full',
                                                    statusDot[p.status],
                                                )}
                                                aria-hidden
                                            />
                                            {p.status_label}
                                        </span>
                                        <span>{p.category}</span>
                                        {p.variants_count > 1 && (
                                            <span>
                                                {p.variants_count} varian
                                            </span>
                                        )}
                                        {p.status !== 'banned' && (
                                            <Link
                                                href={ProductImageController.index(
                                                    p.id,
                                                )}
                                                className="underline-offset-2 hover:text-foreground hover:underline"
                                            >
                                                {p.cover_url
                                                    ? 'Kelola foto'
                                                    : 'Tambah foto'}
                                            </Link>
                                        )}
                                    </p>
                                </div>
                                <div className="flex gap-6 text-sm tabular-nums sm:text-right">
                                    <div className="grid">
                                        <span className="font-semibold">
                                            {priceRange(p)}
                                        </span>
                                        <span
                                            className={cn(
                                                'text-xs',
                                                p.total_stock === 0
                                                    ? 'text-destructive'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {p.total_stock === 0
                                                ? 'Stok habis'
                                                : `Stok ${p.total_stock}`}
                                        </span>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <SimplePagination page={products} />
            </div>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Produk', href: index() }],
};
