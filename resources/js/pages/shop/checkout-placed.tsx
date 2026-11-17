import { Head, Link } from '@inertiajs/react';
import { CircleAlert, CircleCheck, CircleX, Clock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { PayButton } from '@/components/shop/pay-button';
import type { PaymentConfig } from '@/components/shop/pay-button';
import { buttonVariants } from '@/components/ui/button';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { index as ordersIndex } from '@/routes/orders';

type PaymentStatus = 'pending' | 'paid' | 'expired' | 'needs_refund';

type Props = {
    payment: PaymentConfig;
    checkout: {
        code: string;
        payment_status: PaymentStatus;
        payment_status_label: string;
        items_total: number;
        shipping_total: number;
        grand_total: number;
        expires_at: string;
        orders: {
            number: string;
            store: string;
            status_label: string;
            courier: string;
            shipping_cost: number;
            items_total: number;
            items: {
                id: number;
                name: string;
                variant: string | null;
                price: number;
                quantity: number;
            }[];
        }[];
        address: {
            recipient_name: string;
            phone: string;
            street: string;
            region: string;
        } | null;
    };
};

const deadlineFormat = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit',
});

const headers: Record<
    PaymentStatus,
    { icon: LucideIcon; tone: string; title: string; body: string | null }
> = {
    pending: {
        icon: CircleCheck,
        tone: 'text-success',
        title: 'Pesanan dibuat',
        body: null,
    },
    paid: {
        icon: CircleCheck,
        tone: 'text-success',
        title: 'Pembayaran diterima',
        body: 'Terima kasih! Penjual akan segera memproses pesanan Anda.',
    },
    expired: {
        icon: CircleX,
        tone: 'text-muted-foreground',
        title: 'Pesanan dibatalkan',
        body: 'Pembayaran tidak diterima sebelum batas waktu atau gagal, jadi pesanan dibatalkan dan stoknya dikembalikan ke toko.',
    },
    needs_refund: {
        icon: CircleAlert,
        tone: 'text-destructive',
        title: 'Pembayaran akan dikembalikan',
        body: 'Pembayaran Anda diterima setelah pesanan dibatalkan. Tim Zetago akan mengembalikan dana Anda.',
    },
};

export default function CheckoutPlaced({ checkout, payment }: Props) {
    const pending = checkout.payment_status === 'pending';
    const head = headers[checkout.payment_status];
    // JSX needs a capitalised name to treat it as a component.
    const HeadIcon = head.icon;

    return (
        <>
            <Head title={`Pesanan ${checkout.code}`} />

            <div className="mx-auto grid w-full max-w-2xl gap-8 px-4 py-8 md:px-6 md:py-12">
                <header className="grid justify-items-start gap-3">
                    <HeadIcon className={cn('size-9', head.tone)} aria-hidden />
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {head.title}
                    </h1>
                    {head.body && (
                        <p className="text-sm text-muted-foreground">
                            {head.body}
                        </p>
                    )}
                    <p className="text-sm text-muted-foreground">
                        Nomor pesanan{' '}
                        <span className="font-semibold text-foreground tabular-nums">
                            {checkout.code}
                        </span>
                    </p>
                </header>

                {pending && (
                    <section className="grid gap-2 rounded-lg border border-warning/30 p-4">
                        <p className="flex items-center gap-2 font-semibold">
                            <Clock
                                className="size-4 text-warning"
                                aria-hidden
                            />
                            Bayar {formatRupiah(checkout.grand_total)} sebelum{' '}
                            {deadlineFormat.format(
                                new Date(checkout.expires_at),
                            )}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Stok sudah disimpan untuk Anda. Kalau belum dibayar
                            sampai batas waktu, pesanan dibatalkan otomatis.
                        </p>
                        <div className="mt-2">
                            <PayButton code={checkout.code} config={payment} />
                        </div>
                    </section>
                )}

                {checkout.orders.map((order) => (
                    <section
                        key={order.number}
                        aria-label={`Pesanan dari ${order.store}`}
                        className="grid gap-3"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-border pb-2">
                            <h2 className="font-semibold">{order.store}</h2>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {order.number} · {order.status_label}
                            </span>
                        </div>
                        <ul className="grid gap-2 text-sm">
                            {order.items.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex justify-between gap-4"
                                >
                                    <span className="min-w-0">
                                        {item.name}
                                        {item.variant && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · {item.variant}
                                            </span>
                                        )}
                                        <span className="text-muted-foreground">
                                            {' '}
                                            × {item.quantity}
                                        </span>
                                    </span>
                                    <span className="tabular-nums">
                                        {formatRupiah(
                                            item.price * item.quantity,
                                        )}
                                    </span>
                                </li>
                            ))}
                            <li className="flex justify-between gap-4 text-muted-foreground">
                                <span>Ongkir {order.courier}</span>
                                <span className="tabular-nums">
                                    {formatRupiah(order.shipping_cost)}
                                </span>
                            </li>
                        </ul>
                    </section>
                ))}

                <dl className="grid gap-2 border-t border-border pt-4 text-sm">
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">
                            Total harga barang
                        </dt>
                        <dd className="tabular-nums">
                            {formatRupiah(checkout.items_total)}
                        </dd>
                    </div>
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">Total ongkir</dt>
                        <dd className="tabular-nums">
                            {formatRupiah(checkout.shipping_total)}
                        </dd>
                    </div>
                    <div className="flex justify-between text-base font-bold">
                        <dt>Total tagihan</dt>
                        <dd className="tabular-nums">
                            {formatRupiah(checkout.grand_total)}
                        </dd>
                    </div>
                </dl>

                {checkout.address && (
                    <section className="grid gap-1 text-sm">
                        <h2 className="mb-1 font-semibold">Dikirim ke</h2>
                        <p className="font-medium">
                            {checkout.address.recipient_name}{' '}
                            <span className="font-normal text-muted-foreground">
                                · {checkout.address.phone}
                            </span>
                        </p>
                        <p>{checkout.address.street}</p>
                        <p className="text-muted-foreground">
                            {checkout.address.region}
                        </p>
                    </section>
                )}

                <Link
                    href={home()}
                    className={buttonVariants({
                        variant: 'outline',
                        className: 'justify-self-start',
                    })}
                >
                    Lanjut belanja
                </Link>
                <Link
                    href={ordersIndex()}
                    className="justify-self-start text-sm text-primary underline-offset-2 hover:underline"
                >
                    Lihat semua pesanan
                </Link>
            </div>
        </>
    );
}
