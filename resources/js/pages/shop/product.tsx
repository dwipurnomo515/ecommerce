import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Minus, Plus, ShoppingBag } from 'lucide-react';
import { useState } from 'react';
import CartController from '@/actions/App/Http/Controllers/Shop/CartController';
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
import { edit as editProduct } from '@/routes/seller/products';
import { show as showStore } from '@/routes/shop/stores';

type Props = {
    product: {
        id: number;
        name: string;
        slug: string;
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
    isOwner: boolean;
    maxQuantity: number;
};

const LOW_STOCK = 5;

export default function ProductPage({
    product,
    categoryTrail,
    store,
    isOwner,
    maxQuantity,
}: Props) {
    const joined = joinedText(store.joined_at);
    const optionNames = product.options.map((o) => o.name);
    const [selection, setSelection] = useState(() =>
        initialSelection(product.variants),
    );

    const variant = selectedVariant(product.variants, optionNames, selection);
    const range = priceRange(product.variants);
    const soldOut = variant !== null && variant.stock === 0;

    const { auth } = usePage().props;
    const [wantedQuantity, setQuantity] = useState(1);
    const maxForVariant = Math.max(
        1,
        Math.min(variant?.stock ?? 1, maxQuantity),
    );
    // Switching to a variant with less stock lowers the number shown,
    // without a useEffect: derive it instead of syncing state.
    const quantity = Math.min(wantedQuantity, maxForVariant);

    const cart = useForm({ variant_id: 0, quantity: 1 });
    const cartError = cart.errors.quantity ?? cart.errors.variant_id;

    const addToCart = () => {
        if (!variant) {
            return;
        }

        cart.transform(() => ({ variant_id: variant.id, quantity }));
        cart.submit(CartController.store(), {
            preserveScroll: true,
            onSuccess: () => setQuantity(1),
        });
    };

    const addButton = (size: 'lg' | 'default') =>
        auth.user ? (
            <Button
                size={size}
                onClick={addToCart}
                disabled={!variant || soldOut || cart.processing}
            >
                <ShoppingBag />
                {soldOut ? 'Stok habis' : 'Tambah ke keranjang'}
            </Button>
        ) : (
            // Guests: log in first, then land back on this product.
            <Link
                href={CartController.loginToBuy([store.slug, product.slug])}
                className={buttonVariants({ size })}
            >
                Masuk untuk membeli
            </Link>
        );

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

                    {isOwner ? (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/60 p-4 text-sm">
                            <p>Ini produk toko Anda.</p>
                            <Link
                                href={editProduct(product.id)}
                                className={buttonVariants({
                                    variant: 'outline',
                                    size: 'sm',
                                })}
                            >
                                Edit produk
                            </Link>
                        </div>
                    ) : (
                        <div className="grid gap-4">
                            {variant && !soldOut && (
                                <QuantityStepper
                                    value={quantity}
                                    max={maxForVariant}
                                    onChange={setQuantity}
                                />
                            )}
                            <div className="hidden gap-2 md:grid">
                                <div className="grid grid-cols-2 gap-2">
                                    {addButton('lg')}
                                    {/* Checkout is the next step of Tahap 4. */}
                                    <Button
                                        size="lg"
                                        variant="outline"
                                        disabled
                                    >
                                        Beli sekarang
                                    </Button>
                                </div>
                            </div>
                            {cartError && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {cartError}
                                </p>
                            )}
                        </div>
                    )}

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
                {!isOwner && addButton('default')}
            </div>
        </>
    );
}

function QuantityStepper({
    value,
    max,
    onChange,
}: {
    value: number;
    max: number;
    onChange: (value: number) => void;
}) {
    return (
        <div className="flex items-center gap-3">
            <span className="text-sm text-muted-foreground" id="qty-label">
                Jumlah
            </span>
            <div
                role="group"
                aria-labelledby="qty-label"
                className="inline-flex h-10 items-center rounded-md border border-border"
            >
                <button
                    type="button"
                    onClick={() => onChange(value - 1)}
                    disabled={value <= 1}
                    aria-label="Kurangi"
                    className="grid h-full w-10 place-items-center text-muted-foreground hover:text-foreground disabled:opacity-40"
                >
                    <Minus className="size-4" />
                </button>
                <span
                    className="w-10 text-center text-sm font-medium tabular-nums"
                    aria-live="polite"
                >
                    {value}
                </span>
                <button
                    type="button"
                    onClick={() => onChange(value + 1)}
                    disabled={value >= max}
                    aria-label="Tambah"
                    className="grid h-full w-10 place-items-center text-muted-foreground hover:text-foreground disabled:opacity-40"
                >
                    <Plus className="size-4" />
                </button>
            </div>
        </div>
    );
}
