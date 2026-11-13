import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ImageOff, Star } from 'lucide-react';
import { Price } from '@/components/shop/price';
import { discountPercent } from '@/lib/money';
import { cn } from '@/lib/utils';

export type ProductCardData = {
    name: string;
    href: NonNullable<InertiaLinkProps['href']>;
    imageUrl: string | null;
    price: number;
    compareAtPrice?: number | null;
    inStock: boolean;
    store: { name: string; city?: string | null };
    /** Omitted until the product has reviews: no fake ratings. */
    rating?: { average: number; count: number } | null;
};

/**
 * Deliberately quiet: image, name, price, store. No borders, shadows or
 * badge clutter. The photo and the price do the work.
 */
export function ProductCard({
    product,
    className,
}: {
    product: ProductCardData;
    className?: string;
}) {
    const discount = discountPercent(product.price, product.compareAtPrice);

    return (
        <Link
            href={product.href}
            className={cn(
                'group grid min-w-0 content-start gap-2.5 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 focus-visible:ring-offset-background focus-visible:outline-none',
                className,
            )}
        >
            <div className="relative aspect-square overflow-hidden rounded-lg bg-muted">
                {product.imageUrl ? (
                    <img
                        src={product.imageUrl}
                        alt={product.name}
                        loading="lazy"
                        className="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03] motion-reduce:transition-none"
                    />
                ) : (
                    <div className="grid size-full place-items-center text-muted-foreground">
                        <ImageOff className="size-8 opacity-50" aria-hidden />
                        <span className="sr-only">Belum ada foto</span>
                    </div>
                )}

                {discount !== null && product.inStock && (
                    <span className="absolute top-2 left-2 rounded-md bg-background px-1.5 py-0.5 text-xs font-semibold text-sale tabular-nums">
                        −{discount}%
                    </span>
                )}

                {!product.inStock && (
                    <div className="absolute inset-0 grid place-items-center bg-background/60 text-sm font-semibold text-foreground">
                        Stok habis
                    </div>
                )}
            </div>

            <div className="grid gap-1">
                <h3 className="line-clamp-2 text-sm leading-snug text-foreground group-hover:underline group-hover:underline-offset-2">
                    {product.name}
                </h3>
                <Price
                    amount={product.price}
                    compareAt={product.compareAtPrice}
                />
                <p className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                    {product.rating && (
                        <>
                            <span className="inline-flex items-center gap-0.5 font-semibold text-foreground">
                                <Star
                                    className="size-3 fill-current"
                                    aria-hidden
                                />
                                {product.rating.average.toLocaleString(
                                    'id-ID',
                                    {
                                        minimumFractionDigits: 1,
                                        maximumFractionDigits: 1,
                                    },
                                )}
                                <span className="sr-only">
                                    dari 5, {product.rating.count} ulasan
                                </span>
                            </span>
                            <span aria-hidden>·</span>
                        </>
                    )}
                    <span className="truncate">
                        {product.store.name}
                        {product.store.city && ` · ${product.store.city}`}
                    </span>
                </p>
            </div>
        </Link>
    );
}
