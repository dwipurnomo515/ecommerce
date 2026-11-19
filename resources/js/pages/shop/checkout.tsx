import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ImageOff, MapPin, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import CheckoutController from '@/actions/App/Http/Controllers/Shop/CheckoutController';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatRupiah } from '@/lib/money';
import { formatEtd } from '@/lib/shipping';
import { cn } from '@/lib/utils';
import { index as addressesIndex } from '@/routes/addresses';
import { index as cartIndex } from '@/routes/cart';
import { show as checkoutShow } from '@/routes/checkout';

type ShippingStatus =
    | 'ok'
    | 'no_address'
    | 'no_origin'
    | 'no_courier'
    | 'unavailable'
    | 'address_unknown'
    | 'origin_unknown';

type ShippingOption = {
    key: string;
    courier: string;
    courier_name: string;
    service: string;
    description: string;
    cost: number;
    etd: string;
};

type CheckoutItem = {
    id: number;
    name: string;
    imageUrl: string | null;
    variant: string;
    price: number;
    quantity: number;
};

type CheckoutStore = {
    id: number;
    name: string;
    origin_city: string | null;
    items: CheckoutItem[];
    subtotal: number;
    weight: number;
    shipping: { status: ShippingStatus; options: ShippingOption[] };
};

type AddressSummary = {
    id: number;
    label: string | null;
    recipient_name: string;
    phone: string;
    street: string;
    region: string;
    is_primary: boolean;
};

type Props = {
    checkout: {
        stores: CheckoutStore[];
        items_total: number;
        has_problems: boolean;
    };
    addresses: AddressSummary[];
    addressId: number | null;
};

const shippingNotice: Record<Exclude<ShippingStatus, 'ok'>, string> = {
    no_address: 'Pilih alamat pengiriman untuk melihat ongkir.',
    no_origin:
        'Toko ini belum mengisi alamat pengirimannya, jadi ongkir belum bisa dihitung.',
    no_courier: 'Belum ada kurir yang melayani rute ini.',
    unavailable:
        'Ongkir sedang tidak bisa dihitung. Muat ulang halaman sebentar lagi.',
    address_unknown:
        'Wilayah pada alamat ini tidak dikenali layanan ongkir. Ubah alamat lalu pilih ulang kelurahannya.',
    origin_unknown:
        'Alamat pengiriman toko ini tidak dikenali layanan ongkir, jadi produknya belum bisa dikirim. Toko perlu memperbaruinya.',
};

function formatWeight(grams: number): string {
    return grams < 1000
        ? `${grams} g`
        : `${(grams / 1000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} kg`;
}

export default function Checkout({ checkout, addresses, addressId }: Props) {
    const address = addresses.find((a) => a.id === addressId) ?? null;
    const [pickingAddress, setPickingAddress] = useState(false);

    // Cheapest option per store is pre-selected (options come sorted).
    const [chosen, setChosen] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            checkout.stores
                .filter((s) => s.shipping.options.length > 0)
                .map((s) => [s.id, s.shipping.options[0].key]),
        ),
    );

    const shippingCost = (store: CheckoutStore) =>
        store.shipping.options.find((o) => o.key === chosen[store.id])?.cost ??
        null;

    const costs = checkout.stores.map(shippingCost);
    const allShippable = costs.every((c) => c !== null);
    const shippingTotal = costs.reduce<number>((sum, c) => sum + (c ?? 0), 0);

    const { errors } = usePage().props;
    const [placing, setPlacing] = useState(false);
    // One key per visit to this page: if the request is sent twice (double
    // click, flaky network retry), the server recognises it and creates
    // only ONE checkout.
    const [idempotencyKey] = useState(() => crypto.randomUUID());

    const place = () => {
        if (!address || !allShippable) {
            return;
        }

        router.post(
            CheckoutController.store.url(),
            {
                address_id: address.id,
                shipping: chosen,
                idempotency_key: idempotencyKey,
                // What the buyer is looking at. The server recalculates
                // everything and refuses if its total differs.
                expected_total: checkout.items_total + shippingTotal,
            },
            {
                preserveScroll: true,
                onStart: () => setPlacing(true),
                onFinish: () => setPlacing(false),
            },
        );
    };

    return (
        <>
            <Head title="Checkout" />

            <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 pt-6 pb-36 md:px-6 md:pt-10 lg:grid-cols-[1fr_20rem] lg:items-start lg:pb-16">
                <div className="grid gap-8">
                    <div className="grid gap-2">
                        <Link
                            href={cartIndex()}
                            className="inline-flex w-fit items-center gap-0.5 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ChevronLeft className="size-4" aria-hidden />
                            Keranjang
                        </Link>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Checkout
                        </h1>
                    </div>

                    {checkout.has_problems && (
                        <p className="flex gap-2 rounded-lg border border-warning/30 p-3 text-sm text-muted-foreground">
                            <TriangleAlert
                                className="mt-0.5 size-4 shrink-0 text-warning"
                                aria-hidden
                            />
                            <span>
                                Beberapa barang di keranjang sedang tidak bisa
                                dibeli dan tidak ikut di sini.{' '}
                                <Link
                                    href={cartIndex()}
                                    className="font-medium text-foreground underline underline-offset-2"
                                >
                                    Lihat keranjang
                                </Link>
                            </span>
                        </p>
                    )}

                    <section
                        aria-labelledby="address-heading"
                        className="grid gap-3"
                    >
                        <h2 id="address-heading" className="font-semibold">
                            Alamat pengiriman
                        </h2>
                        {address ? (
                            <div className="flex items-start justify-between gap-4 rounded-lg border border-border p-4">
                                <div className="grid gap-0.5 text-sm">
                                    <p className="font-medium">
                                        {address.recipient_name}{' '}
                                        <span className="font-normal text-muted-foreground tabular-nums">
                                            · {address.phone}
                                        </span>
                                    </p>
                                    <p>{address.street}</p>
                                    <p className="text-muted-foreground">
                                        {address.region}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setPickingAddress(true)}
                                >
                                    Ganti
                                </Button>
                            </div>
                        ) : (
                            <div className="grid justify-items-start gap-3 rounded-lg border border-dashed border-border p-4 text-sm">
                                <p className="text-muted-foreground">
                                    {addresses.length === 0
                                        ? 'Anda belum punya alamat pengiriman.'
                                        : 'Pilih alamat pengiriman.'}
                                </p>
                                {addresses.length === 0 ? (
                                    <Link
                                        href={addressesIndex()}
                                        className={buttonVariants({
                                            size: 'sm',
                                        })}
                                    >
                                        <MapPin />
                                        Tambah alamat
                                    </Link>
                                ) : (
                                    <Button
                                        size="sm"
                                        onClick={() => setPickingAddress(true)}
                                    >
                                        Pilih alamat
                                    </Button>
                                )}
                            </div>
                        )}
                    </section>

                    {checkout.stores.map((store) => (
                        <StoreParcel
                            key={store.id}
                            store={store}
                            chosen={chosen[store.id]}
                            onChoose={(key) =>
                                setChosen((c) => ({ ...c, [store.id]: key }))
                            }
                        />
                    ))}
                </div>

                <Summary
                    itemsTotal={checkout.items_total}
                    shippingTotal={allShippable ? shippingTotal : null}
                    canPlace={address !== null}
                    placing={placing}
                    error={errors.checkout}
                    onPlace={place}
                />
            </div>

            <AddressPicker
                open={pickingAddress}
                onClose={() => setPickingAddress(false)}
                addresses={addresses}
                currentId={addressId}
            />
        </>
    );
}

function StoreParcel({
    store,
    chosen,
    onChoose,
}: {
    store: CheckoutStore;
    chosen: string | undefined;
    onChoose: (key: string) => void;
}) {
    return (
        <section
            aria-label={`Pesanan dari ${store.name}`}
            className="grid gap-4"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-border pb-2">
                <h2 className="font-semibold">{store.name}</h2>
                <p className="text-xs text-muted-foreground">
                    {store.origin_city &&
                        `Dikirim dari ${store.origin_city} · `}
                    {formatWeight(store.weight)}
                </p>
            </div>

            <ul className="grid gap-3">
                {store.items.map((item) => (
                    <li
                        key={item.id}
                        className="grid grid-cols-[3.5rem_1fr_auto] items-center gap-3 text-sm"
                    >
                        <div className="aspect-square overflow-hidden rounded-md bg-muted">
                            {item.imageUrl ? (
                                <img
                                    src={item.imageUrl}
                                    alt=""
                                    loading="lazy"
                                    className="size-full object-cover"
                                />
                            ) : (
                                <span className="grid size-full place-items-center text-muted-foreground">
                                    <ImageOff className="size-4" aria-hidden />
                                </span>
                            )}
                        </div>
                        <div className="grid min-w-0">
                            <span className="truncate font-medium">
                                {item.name}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {item.variant && `${item.variant} · `}
                                {item.quantity} × {formatRupiah(item.price)}
                            </span>
                        </div>
                        <span className="tabular-nums">
                            {formatRupiah(item.price * item.quantity)}
                        </span>
                    </li>
                ))}
            </ul>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">Pengiriman</legend>
                {store.shipping.status === 'ok' ? (
                    <div className="grid gap-2 sm:grid-cols-2">
                        {store.shipping.options.map((option) => (
                            <label
                                key={option.key}
                                className="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 text-sm has-checked:border-primary has-checked:bg-primary-soft has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                            >
                                <input
                                    type="radio"
                                    name={`shipping-${store.id}`}
                                    value={option.key}
                                    checked={chosen === option.key}
                                    onChange={() => onChoose(option.key)}
                                    className="mt-1 accent-primary"
                                />
                                <span className="grid flex-1 gap-0.5">
                                    <span className="font-medium">
                                        {option.courier.toUpperCase()}{' '}
                                        {option.service}
                                    </span>
                                    {option.etd && (
                                        <span className="text-xs text-muted-foreground">
                                            Estimasi {formatEtd(option.etd)}
                                        </span>
                                    )}
                                </span>
                                <span className="font-semibold tabular-nums">
                                    {formatRupiah(option.cost)}
                                </span>
                            </label>
                        ))}
                    </div>
                ) : (
                    <p
                        className={cn(
                            'rounded-lg border p-3 text-sm',
                            store.shipping.status === 'no_address'
                                ? 'border-border text-muted-foreground'
                                : 'border-warning/30 text-muted-foreground',
                        )}
                    >
                        {shippingNotice[store.shipping.status]}
                        {store.shipping.status === 'address_unknown' && (
                            <>
                                {' '}
                                <Link
                                    href={addressesIndex()}
                                    className="font-medium text-foreground underline underline-offset-4"
                                >
                                    Ubah alamat
                                </Link>
                            </>
                        )}
                    </p>
                )}
            </fieldset>
        </section>
    );
}

function Summary({
    itemsTotal,
    shippingTotal,
    canPlace,
    placing,
    error,
    onPlace,
}: {
    itemsTotal: number;
    /** null while some store has no shipping option chosen/available. */
    shippingTotal: number | null;
    canPlace: boolean;
    placing: boolean;
    error: string | undefined;
    onPlace: () => void;
}) {
    const rows = (
        <dl className="grid gap-2 text-sm">
            <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Total harga barang</dt>
                <dd className="tabular-nums">{formatRupiah(itemsTotal)}</dd>
            </div>
            <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Total ongkir</dt>
                <dd className="tabular-nums">
                    {shippingTotal === null ? '—' : formatRupiah(shippingTotal)}
                </dd>
            </div>
            <div className="flex justify-between gap-4 border-t border-border pt-2 text-base font-bold">
                <dt>Total tagihan</dt>
                <dd className="tabular-nums">
                    {shippingTotal === null
                        ? '—'
                        : formatRupiah(itemsTotal + shippingTotal)}
                </dd>
            </div>
        </dl>
    );

    const button = (
        <>
            {error && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
            <Button
                size="lg"
                className="w-full"
                disabled={shippingTotal === null || !canPlace || placing}
                onClick={onPlace}
            >
                {placing ? 'Membuat pesanan…' : 'Buat pesanan'}
            </Button>
        </>
    );

    return (
        <>
            <aside className="hidden gap-4 rounded-lg border border-border p-5 lg:sticky lg:top-6 lg:grid">
                <h2 className="font-semibold">Ringkasan</h2>
                {rows}
                {button}
                <p className="text-xs text-muted-foreground">
                    Stok disimpan untuk Anda selama 24 jam sampai pembayaran
                    selesai.
                </p>
            </aside>
            <div className="fixed inset-x-0 bottom-0 z-10 grid gap-3 border-t border-border bg-background px-4 py-3 lg:hidden">
                {rows}
                {button}
            </div>
        </>
    );
}

function AddressPicker({
    open,
    onClose,
    addresses,
    currentId,
}: {
    open: boolean;
    onClose: () => void;
    addresses: AddressSummary[];
    currentId: number | null;
}) {
    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-h-[85svh] overflow-y-auto">
                <DialogTitle>Pilih alamat</DialogTitle>
                <DialogDescription>
                    Ongkir dihitung ulang untuk alamat yang dipilih.
                </DialogDescription>
                <ul className="grid gap-2">
                    {addresses.map((a) => (
                        <li key={a.id}>
                            <button
                                type="button"
                                onClick={() => {
                                    onClose();
                                    // A GET: the server re-quotes for this address.
                                    router.get(
                                        checkoutShow.url({
                                            query: { alamat: a.id },
                                        }),
                                        {},
                                        { preserveScroll: true },
                                    );
                                }}
                                className={cn(
                                    'grid w-full gap-0.5 rounded-lg border p-3 text-left text-sm hover:bg-muted/50',
                                    a.id === currentId
                                        ? 'border-primary bg-primary-soft'
                                        : 'border-border',
                                )}
                            >
                                <span className="font-medium">
                                    {a.recipient_name}
                                    {a.label && (
                                        <span className="font-normal text-muted-foreground">
                                            {' '}
                                            · {a.label}
                                        </span>
                                    )}
                                    {a.is_primary && (
                                        <span className="ml-2 text-xs font-semibold text-primary">
                                            Utama
                                        </span>
                                    )}
                                </span>
                                <span>{a.street}</span>
                                <span className="text-muted-foreground">
                                    {a.region}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
                <Link
                    href={addressesIndex()}
                    className="text-sm text-primary underline-offset-2 hover:underline"
                >
                    Kelola alamat
                </Link>
            </DialogContent>
        </Dialog>
    );
}
