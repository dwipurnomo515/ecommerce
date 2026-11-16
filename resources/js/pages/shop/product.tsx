import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Price } from '@/components/shop/price';
import { ProductGallery } from '@/components/shop/product-gallery';
import { joinedText, StoreAvatar } from '@/components/shop/store-avatar';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    choose,
    initialSelection,
    priceRange,
    selectedVariant,
    valueState,
} from '@/lib/variant-picker';
import type { BuyerVariant } from '@/lib/variant-picker';
import { show as showStore } from '@/routes/shop/stores';

type Props = {
    product: {
        id: number;
        name: string;
        description: string | null;
        summary: string;
        options: { name: string; values: string[] }[];
        images: { id: number; url: string }[];
        variants: BuyerVariant[];
    };
    categoryTrail: { id: number; name: string }[];
    store: {
        name: string;
        slug: string;
        joined_at: string | null;
        products_count: number;
    };
};

const LOW_STOCK = 5;

export default function ProductPage({ product, categoryTrail, store }: Props) {
    const joined = joinedText(store.joined_at);
    const optionNames = product.options.map((o) => o.name);
    const [selection, setSelection] = useState(() =>
        initialSelection(product.variants),
    );

    const variant = selectedVariant(product.variants, optionNames, selection);
    const range = priceRange(product.variants);
    const soldOut = variant !== null && variant.stock === 0;

    const price = variant ? (
        <Price
            amount={variant.price}
            compareAt={variant.compare_at_price}
            size="lg"
        />
    ) : (
        <Price amount={range.min} size="lg" />
    );

    return (
        <>
            <Head title={product.name}>
                <meta
                    head-key="description"
                    name="description"
                    content={product.summary}
                />
            </Head>

            <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 pt-4 pb-28 md:grid-cols-2 md:gap-12 md:px-6 md:pt-8 md:pb-16">
                <ProductGallery images={product.images} name={product.name} />

                <div className="grid content-start gap-6">
                    <div className="grid gap-2">
                        <nav
                            aria-label="Kategori"
                            className="text-xs text-muted-foreground"
                        >
                            {categoryTrail.map((c) => c.name).join(' › ')}
                        </nav>
                        <h1 className="text-2xl leading-tight font-semibold tracking-tight md:text-3xl">
                            {product.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            dari{' '}
                            <Link
                                href={showStore(store.slug)}
                                className="font-medium text-foreground underline-offset-2 hover:underline"
                            >
                                {store.name}
                            </Link>
                        </p>
                    </div>

                    {price}

                    {product.options.map((option) => (
                        <fieldset key={option.name} className="grid gap-2.5">
                            <legend className="mb-2.5 text-sm">
                                <span className="text-muted-foreground">
                                    {option.name}:
                                </span>{' '}
                                <span className="font-medium">
                                    {selection[option.name] ?? 'Pilih'}
                                </span>
                            </legend>
                            <div className="flex flex-wrap gap-2">
                                {option.values.map((value) => {
                                    const state = valueState(
                                        product.variants,
                                        selection,
                                        option.name,
                                        value,
                                    );
                                    const checked =
                                        selection[option.name] === value;

                                    return (
                                        <button
                                            key={value}
                                            type="button"
                                            aria-pressed={checked}
                                            onClick={() =>
                                                setSelection(
                                                    choose(
                                                        product.variants,
                                                        selection,
                                                        option.name,
                                                        value,
                                                    ),
                                                )
                                            }
                                            className={cn(
                                                'min-h-10 min-w-12 rounded-md border px-3.5 text-sm transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                checked
                                                    ? 'border-primary bg-primary-soft font-medium text-foreground'
                                                    : 'border-border hover:border-foreground/40',
                                                state !== 'available' &&
                                                    !checked &&
                                                    'text-muted-foreground',
                                                state === 'sold-out' &&
                                                    'line-through decoration-muted-foreground/60',
                                                state === 'missing' &&
                                                    'border-dashed',
                                            )}
                                        >
                                            {value}
                                            {state === 'sold-out' && (
                                                <span className="sr-only">
                                                    {' '}
                                                    (habis)
                                                </span>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        </fieldset>
                    ))}

                    {variant && (
                        <p
                            className={cn(
                                'text-sm',
                                soldOut
                                    ? 'font-medium text-destructive'
                                    : variant.stock <= LOW_STOCK
                                      ? 'font-medium text-warning'
                                      : 'text-muted-foreground',
                            )}
                            aria-live="polite"
                        >
                            {soldOut
                                ? 'Stok habis untuk pilihan ini'
                                : variant.stock <= LOW_STOCK
                                  ? `Sisa ${variant.stock}, segera habis`
                                  : `Stok tersedia (${variant.stock})`}
                        </p>
                    )}

                    <BuyButtons disabled />

                    <section
                        aria-label="Penjual"
                        className="flex items-center gap-3 border-y border-border py-4"
                    >
                        <StoreAvatar name={store.name} />
                        <div className="grid min-w-0 flex-1 gap-0.5">
                            <p className="truncate font-medium">{store.name}</p>
                            <p className="text-xs text-muted-foreground">
                                {store.products_count} produk
                                {joined && ` · ${joined}`}
                            </p>
                        </div>
                        <Link
                            href={showStore(store.slug)}
                            className={buttonVariants({
                                variant: 'outline',
                                size: 'sm',
                            })}
                        >
                            Kunjungi toko
                        </Link>
                    </section>

                    {product.description && (
                        <section className="grid gap-2">
                            <h2 className="font-semibold">Deskripsi</h2>
                            {/* pre-line keeps the seller's line breaks; React escapes the text. */}
                            <p className="text-sm leading-relaxed whitespace-pre-line text-muted-foreground">
                                {product.description}
                            </p>
                        </section>
                    )}
                </div>
            </div>

            {/* Phone: price and buy button always within thumb reach */}
            <div className="fixed inset-x-0 bottom-0 z-10 flex items-center gap-3 border-t border-border bg-background px-4 py-3 md:hidden">
                <div className="min-w-0 flex-1">
                    {variant ? (
                        <Price
                            amount={variant.price}
                            compareAt={variant.compare_at_price}
                        />
                    ) : (
                        <Price amount={range.min} />
                    )}
                </div>
                <Button disabled>Tambah ke keranjang</Button>
            </div>
        </>
    );
}

/**
 * Cart and checkout arrive in Tahap 4. Until then the buttons are shown
 * (so the layout is final) but honestly disabled with a reason.
 */
function BuyButtons({ disabled }: { disabled: boolean }) {
    return (
        <div className="hidden gap-2 md:grid">
            <div className="grid grid-cols-2 gap-2">
                <Button size="lg" disabled={disabled}>
                    Tambah ke keranjang
                </Button>
                <Button size="lg" variant="outline" disabled={disabled}>
                    Beli sekarang
                </Button>
            </div>
            {disabled && (
                <p className="text-xs text-muted-foreground">
                    Keranjang dan pembayaran sedang disiapkan.
                </p>
            )}
        </div>
    );
}
