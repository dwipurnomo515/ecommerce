import { Head, Link, router } from '@inertiajs/react';
import { ImageOff, ShoppingBag, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CartController from '@/actions/App/Http/Controllers/Shop/CartController';
import { Price } from '@/components/shop/price';
import { QuantityStepper } from '@/components/shop/quantity-stepper';
import { Button, buttonVariants } from '@/components/ui/button';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { show as checkoutShow } from '@/routes/checkout';
import { show as showStore } from '@/routes/shop/stores';

type ItemStatus = 'ok' | 'unavailable' | 'sold_out' | 'not_enough';

type CartRow = {
    id: number;
    name: string;
    href: string | null;
    imageUrl: string | null;
    variant: string;
    price: number;
    compareAtPrice: number | null;
    /** The old price, only when it changed since the buyer last saw it. */
    seenPrice: number | null;
    quantity: number;
    stock: number;
    maxQuantity: number;
    status: ItemStatus;
};

type CartStore = {
    id: number;
    name: string;
    slug: string;
    items: CartRow[];
    subtotal: number;
};

type Props = {
    cart: {
        stores: CartStore[];
        total: number;
        count: number;
        has_problems: boolean;
    };
};

export default function CartPage({ cart }: Props) {
    const [busy, setBusy] = useState<number | null>(null);
    const [error, setError] = useState<{ id: number; message: string } | null>(
        null,
    );

    const visit = (id: number, send: () => void) => {
        setBusy(id);
        setError(null);
        send();
    };

    const options = (id: number) => ({
        preserveScroll: true,
        onError: (errors: Record<string, string>) =>
            setError({
                id,
                message: errors.quantity ?? 'Gagal menyimpan perubahan.',
            }),
        onFinish: () => setBusy(null),
    });

    const setQuantity = (item: CartRow, quantity: number) =>
        visit(item.id, () =>
            router.patch(
                CartController.update.url(item.id),
                { quantity },
                options(item.id),
            ),
        );

    const remove = (item: CartRow) =>
        visit(item.id, () =>
            router.delete(
                CartController.destroy.url(item.id),
                options(item.id),
            ),
        );

    if (cart.stores.length === 0) {
        return (
            <>
                <Head title="Keranjang" />
                <div className="mx-auto grid w-full max-w-md justify-items-center gap-3 px-4 py-20 text-center">
                    <ShoppingBag
                        className="size-10 text-muted-foreground"
                        aria-hidden
                    />
                    <h1 className="text-xl font-semibold">
                        Keranjang Anda kosong
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Barang yang Anda tambahkan akan muncul di sini.
                    </p>
                    <Link href={home()} className={buttonVariants()}>
                        Mulai belanja
                    </Link>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Keranjang" />

            <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 pt-6 pb-32 md:px-6 md:pt-10 lg:grid-cols-[1fr_20rem] lg:items-start lg:pb-16">
                <div className="grid gap-8">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Keranjang
                    </h1>

                    {cart.stores.map((store) => (
                        <section
                            key={store.id}
                            aria-label={`Barang dari ${store.name}`}
                            className="grid gap-4"
                        >
                            <div className="flex items-baseline justify-between gap-4 border-b border-border pb-2">
                                <Link
                                    href={showStore(store.slug)}
                                    className="font-semibold underline-offset-2 hover:underline"
                                >
                                    {store.name}
                                </Link>
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {formatRupiah(store.subtotal)}
                                </span>
                            </div>

                            <ul className="grid gap-5">
                                {store.items.map((item) => (
                                    <CartLine
                                        key={item.id}
                                        item={item}
                                        busy={busy === item.id}
                                        error={
                                            error?.id === item.id
                                                ? error.message
                                                : null
                                        }
                                        onQuantity={(q) => setQuantity(item, q)}
                                        onRemove={() => remove(item)}
                                    />
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>

                <Summary cart={cart} />
            </div>
        </>
    );
}

function CartLine({
    item,
    busy,
    error,
    onQuantity,
    onRemove,
}: {
    item: CartRow;
    busy: boolean;
    error: string | null;
    onQuantity: (quantity: number) => void;
    onRemove: () => void;
}) {
    const buyable = item.status === 'ok' || item.status === 'not_enough';

    const notice: Record<Exclude<ItemStatus, 'ok'>, string> = {
        unavailable: 'Produk ini sudah tidak dijual.',
        sold_out: 'Stok habis.',
        not_enough: `Stok tersisa ${item.stock}. Kurangi jumlahnya untuk melanjutkan.`,
    };

    const name = (
        <span className="line-clamp-2 text-sm leading-snug font-medium">
            {item.name}
        </span>
    );

    return (
        <li
            className={cn(
                'grid grid-cols-[5rem_1fr] gap-4',
                busy && 'opacity-60',
            )}
        >
            <div
                className={cn(
                    'aspect-square overflow-hidden rounded-md bg-muted',
                    item.status === 'unavailable' && 'grayscale',
                )}
            >
                {item.imageUrl ? (
                    <img
                        src={item.imageUrl}
                        alt=""
                        loading="lazy"
                        className="size-full object-cover"
                    />
                ) : (
                    <span className="grid size-full place-items-center text-muted-foreground">
                        <ImageOff className="size-5" aria-hidden />
                    </span>
                )}
            </div>

            <div className="grid min-w-0 gap-2">
                <div className="grid gap-0.5">
                    {item.href ? (
                        <Link
                            href={item.href}
                            className="underline-offset-2 hover:underline"
                        >
                            {name}
                        </Link>
                    ) : (
                        name
                    )}
                    {item.variant && (
                        <span className="text-xs text-muted-foreground">
                            {item.variant}
                        </span>
                    )}
                </div>

                <Price amount={item.price} compareAt={item.compareAtPrice} />

                {item.seenPrice !== null && item.status !== 'unavailable' && (
                    <p className="text-xs text-muted-foreground">
                        Harga {item.price > item.seenPrice ? 'naik' : 'turun'}{' '}
                        dari {formatRupiah(item.seenPrice)}.
                    </p>
                )}

                {item.status !== 'ok' && (
                    <p
                        className={cn(
                            'text-xs font-medium',
                            item.status === 'not_enough'
                                ? 'text-warning'
                                : 'text-destructive',
                        )}
                    >
                        {notice[item.status]}
                    </p>
                )}

                <div className="flex items-center justify-between gap-3">
                    {buyable ? (
                        <QuantityStepper
                            value={item.quantity}
                            // Allow stepping down from an over-stock amount.
                            max={Math.max(item.maxQuantity, 1)}
                            onChange={onQuantity}
                            label={`Jumlah ${item.name}`}
                            disabled={busy}
                            size="sm"
                        />
                    ) : (
                        <span className="text-xs text-muted-foreground tabular-nums">
                            Jumlah {item.quantity}
                        </span>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onRemove}
                        disabled={busy}
                        className="text-muted-foreground hover:text-destructive"
                    >
                        <Trash2 />
                        Hapus
                    </Button>
                </div>

                {error && (
                    <p role="alert" className="text-xs text-destructive">
                        {error}
                    </p>
                )}
            </div>
        </li>
    );
}

function Summary({ cart }: { cart: Props['cart'] }) {
    const content = (
        <>
            <div className="flex items-baseline justify-between gap-4">
                <span className="text-sm text-muted-foreground">
                    Total ({cart.count} barang)
                </span>
                <span className="text-lg font-bold tabular-nums">
                    {formatRupiah(cart.total)}
                </span>
            </div>
            {cart.count > 0 ? (
                <Link
                    href={checkoutShow()}
                    className={buttonVariants({
                        size: 'lg',
                        className: 'w-full',
                    })}
                >
                    Lanjut ke checkout
                </Link>
            ) : (
                <Button size="lg" disabled className="w-full">
                    Lanjut ke checkout
                </Button>
            )}
        </>
    );

    return (
        <>
            {/* Desktop: a quiet panel that follows the scroll */}
            <aside className="hidden gap-4 rounded-lg border border-border p-5 lg:sticky lg:top-6 lg:grid">
                <h2 className="font-semibold">Ringkasan</h2>
                {content}
                <p className="text-xs text-muted-foreground">
                    Ongkir dihitung per toko saat checkout.
                    {cart.has_problems &&
                        ' Barang yang bermasalah tidak dihitung.'}
                </p>
            </aside>

            {/* Phone: total and button always at hand */}
            <div className="fixed inset-x-0 bottom-0 z-10 grid gap-2 border-t border-border bg-background px-4 py-3 lg:hidden">
                {content}
            </div>
        </>
    );
}
