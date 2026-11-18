import { Form, Head, Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import SellerOrderController from '@/actions/App/Http/Controllers/Seller/OrderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SimplePagination } from '@/components/simple-pagination';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/date';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index } from '@/routes/seller/orders';
import type { Paginated } from '@/types/pagination';

type Status = 'paid' | 'processing' | 'shipped' | 'completed';

type SellerOrder = {
    id: number;
    number: string;
    created_at: string | null;
    shipped_at: string | null;
    items_total: number;
    courier: string;
    tracking_number: string | null;
    weight_grams: number;
    ship_to: {
        recipient_name: string;
        phone: string;
        street: string;
        notes: string | null;
        region: string;
    };
    items: {
        id: number;
        name: string;
        variant: string | null;
        sku: string | null;
        quantity: number;
    }[];
};

type Props = {
    orders: Paginated<SellerOrder>;
    status: Status;
    tabs: { value: Status; label: string; count: number }[];
};

const emptyText: Record<Status, string> = {
    paid: 'Tidak ada pesanan baru. Pesanan yang sudah dibayar pembeli akan muncul di sini.',
    processing: 'Tidak ada pesanan yang sedang dikemas.',
    shipped: 'Tidak ada pesanan dalam pengiriman.',
    completed: 'Belum ada pesanan yang selesai.',
};

export default function SellerOrders({ orders, status, tabs }: Props) {
    return (
        <>
            <Head title="Pesanan masuk" />

            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <Heading
                    title="Pesanan masuk"
                    description="Pesanan yang sudah dibayar pembeli. Proses yang paling lama dulu."
                />

                <nav
                    aria-label="Status pesanan"
                    className="-mb-2 flex gap-1 overflow-x-auto border-b border-border"
                >
                    {tabs.map((tab) => {
                        const current = tab.value === status;

                        return (
                            <Link
                                key={tab.value}
                                href={index({ query: { status: tab.value } })}
                                preserveScroll
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm whitespace-nowrap',
                                    current
                                        ? 'border-primary font-semibold text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.label}
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 text-xs tabular-nums',
                                        // Work waiting: make it stand out.
                                        tab.value === 'paid' && tab.count > 0
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {tab.count}
                                </span>
                            </Link>
                        );
                    })}
                </nav>

                {orders.data.length === 0 ? (
                    <div className="grid justify-items-center gap-2 rounded-lg border border-dashed border-border px-6 py-14 text-center">
                        <Inbox
                            className="size-6 text-muted-foreground"
                            aria-hidden
                        />
                        <p className="max-w-sm text-sm text-muted-foreground">
                            {emptyText[status]}
                        </p>
                    </div>
                ) : (
                    <ul className="grid gap-4">
                        {orders.data.map((order) => (
                            <OrderCard
                                key={order.id}
                                order={order}
                                status={status}
                            />
                        ))}
                    </ul>
                )}

                <SimplePagination page={orders} />
            </div>
        </>
    );
}

SellerOrders.layout = {
    breadcrumbs: [{ title: 'Pesanan masuk', href: index() }],
};

function OrderCard({ order, status }: { order: SellerOrder; status: Status }) {
    return (
        <li className="grid gap-4 rounded-lg border border-border bg-card p-4">
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <p className="font-semibold tabular-nums">{order.number}</p>
                <p className="text-xs text-muted-foreground tabular-nums">
                    Dipesan {formatDate(order.created_at)} ·{' '}
                    {formatRupiah(order.items_total)}
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <section
                    aria-label="Barang"
                    className="grid content-start gap-1.5 text-sm"
                >
                    <h3 className="text-xs font-medium text-muted-foreground">
                        Barang
                    </h3>
                    <ul className="grid gap-1">
                        {order.items.map((item) => (
                            <li
                                key={item.id}
                                className="flex justify-between gap-3"
                            >
                                <span className="min-w-0">
                                    {item.name}
                                    {item.variant && (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {item.variant}
                                        </span>
                                    )}
                                    {item.sku && (
                                        <span className="block text-xs text-muted-foreground">
                                            SKU {item.sku}
                                        </span>
                                    )}
                                </span>
                                <span className="font-semibold tabular-nums">
                                    × {item.quantity}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>

                <section
                    aria-label="Kirim ke"
                    className="grid content-start gap-1.5 text-sm"
                >
                    <h3 className="text-xs font-medium text-muted-foreground">
                        Kirim ke · {order.courier} ·{' '}
                        {(order.weight_grams / 1000).toLocaleString('id-ID', {
                            maximumFractionDigits: 2,
                        })}{' '}
                        kg
                    </h3>
                    <p className="font-medium">
                        {order.ship_to.recipient_name}{' '}
                        <span className="font-normal text-muted-foreground tabular-nums">
                            · {order.ship_to.phone}
                        </span>
                    </p>
                    <p>{order.ship_to.street}</p>
                    <p className="text-muted-foreground">
                        {order.ship_to.region}
                    </p>
                    {order.ship_to.notes && (
                        <p className="text-muted-foreground">
                            Patokan: {order.ship_to.notes}
                        </p>
                    )}
                </section>
            </div>

            <div className="border-t border-border pt-4">
                {status === 'paid' && (
                    <Link
                        href={SellerOrderController.process(order.id)}
                        as="button"
                        preserveScroll
                        className={buttonVariants()}
                    >
                        Proses pesanan
                    </Link>
                )}

                {status === 'processing' && (
                    <Form
                        {...SellerOrderController.ship.form(order.id)}
                        options={{ preserveScroll: true }}
                        className="grid gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <label
                                    htmlFor={`resi-${order.id}`}
                                    className="text-sm font-medium"
                                >
                                    Nomor resi {order.courier}
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    <Input
                                        id={`resi-${order.id}`}
                                        name="tracking_number"
                                        required
                                        minLength={6}
                                        maxLength={40}
                                        autoComplete="off"
                                        aria-invalid={!!errors.tracking_number}
                                        placeholder="Contoh: JP1234567890"
                                        className="max-w-xs uppercase"
                                    />
                                    <Button type="submit" disabled={processing}>
                                        Tandai dikirim
                                    </Button>
                                </div>
                                <InputError message={errors.tracking_number} />
                            </>
                        )}
                    </Form>
                )}

                {status === 'shipped' && (
                    <p className="text-sm text-muted-foreground">
                        Dikirim {formatDate(order.shipped_at)} · Resi{' '}
                        <span className="font-semibold text-foreground tabular-nums">
                            {order.tracking_number}
                        </span>
                        . Menunggu konfirmasi pembeli.
                    </p>
                )}

                {status === 'completed' && (
                    <p className="text-sm text-muted-foreground">
                        Selesai · Resi{' '}
                        <span className="tabular-nums">
                            {order.tracking_number}
                        </span>
                    </p>
                )}
            </div>
        </li>
    );
}
