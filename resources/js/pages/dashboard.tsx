import { Head, Link } from '@inertiajs/react';
import { ChevronRight, CreditCard, PackageCheck, Truck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { buttonVariants } from '@/components/ui/button';
import { formatDate } from '@/lib/date';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import { dashboard, home } from '@/routes';
import { placed } from '@/routes/checkout';
import { index as myOrders } from '@/routes/orders';

type ActiveStatus = 'paid' | 'processing' | 'shipped';

type BuyerSummary = {
    counts: { unpaid: number; in_progress: number; shipped: number };
    unpaid: { code: string; grand_total: number; expires_at: string }[];
    active: {
        number: string;
        store: string;
        status: ActiveStatus;
        status_label: string;
        tracking_number: string | null;
        checkout_code: string;
    }[];
};

const statusTone: Record<ActiveStatus, string> = {
    paid: 'text-muted-foreground',
    processing: 'text-warning',
    shipped: 'text-success',
};

function Stat({
    icon: Icon,
    label,
    value,
    hint,
}: {
    icon: LucideIcon;
    label: string;
    value: number;
    hint: string;
}) {
    return (
        <Link
            href={myOrders()}
            className="grid gap-1 rounded-lg border border-border p-4 hover:bg-muted/40"
        >
            <span className="flex items-center gap-2 text-sm text-muted-foreground">
                <Icon className="size-4" aria-hidden />
                {label}
            </span>
            <span className="text-2xl font-semibold tabular-nums">{value}</span>
            <span className="text-xs text-muted-foreground">{hint}</span>
        </Link>
    );
}

export default function Dashboard({ buyer }: { buyer: BuyerSummary }) {
    const nothingGoingOn =
        buyer.counts.unpaid +
            buyer.counts.in_progress +
            buyer.counts.shipped ===
        0;

    return (
        <>
            <Head title="Ringkasan" />

            <div className="grid w-full max-w-4xl gap-8 p-4 md:p-6">
                <section className="grid gap-4" aria-labelledby="belanja">
                    <h1
                        id="belanja"
                        className="text-2xl font-semibold tracking-tight"
                    >
                        Belanjaanmu
                    </h1>

                    <div className="grid gap-3 sm:grid-cols-3">
                        <Stat
                            icon={CreditCard}
                            label="Menunggu pembayaran"
                            value={buyer.counts.unpaid}
                            hint="Bayar sebelum batas waktunya"
                        />
                        <Stat
                            icon={PackageCheck}
                            label="Diproses penjual"
                            value={buyer.counts.in_progress}
                            hint="Sedang disiapkan toko"
                        />
                        <Stat
                            icon={Truck}
                            label="Dalam pengiriman"
                            value={buyer.counts.shipped}
                            hint="Konfirmasi setelah diterima"
                        />
                    </div>

                    {buyer.unpaid.length > 0 && (
                        <ul className="divide-y divide-border rounded-lg border border-warning/40">
                            {buyer.unpaid.map((c) => (
                                <li
                                    key={c.code}
                                    className="flex flex-wrap items-center gap-x-4 gap-y-2 p-4"
                                >
                                    <div className="grid min-w-0 flex-1 gap-0.5">
                                        <p className="text-sm font-semibold tabular-nums">
                                            {c.code}
                                        </p>
                                        <p className="text-xs text-muted-foreground tabular-nums">
                                            Bayar sebelum{' '}
                                            {formatDate(c.expires_at)}
                                        </p>
                                    </div>
                                    <span className="font-semibold tabular-nums">
                                        {formatRupiah(c.grand_total)}
                                    </span>
                                    <Link
                                        href={placed(c.code)}
                                        className={buttonVariants({
                                            size: 'sm',
                                        })}
                                    >
                                        Bayar
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}

                    {buyer.active.length > 0 && (
                        <div className="grid gap-2">
                            <h2 className="text-sm font-medium text-muted-foreground">
                                Pesanan berjalan
                            </h2>
                            <ul className="divide-y divide-border rounded-lg border border-border">
                                {buyer.active.map((o) => (
                                    <li key={o.number}>
                                        <Link
                                            href={placed(o.checkout_code)}
                                            className="flex items-center gap-4 p-4 hover:bg-muted/40"
                                        >
                                            <div className="grid min-w-0 flex-1 gap-0.5">
                                                <p className="truncate text-sm font-medium">
                                                    {o.store}
                                                </p>
                                                <p className="text-xs text-muted-foreground tabular-nums">
                                                    {o.number}
                                                    {o.tracking_number &&
                                                        ` · resi ${o.tracking_number}`}
                                                </p>
                                            </div>
                                            <span
                                                className={cn(
                                                    'text-xs font-medium',
                                                    statusTone[o.status],
                                                )}
                                            >
                                                {o.status_label}
                                            </span>
                                            <ChevronRight
                                                className="size-4 shrink-0 text-muted-foreground"
                                                aria-hidden
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {nothingGoingOn && (
                        <div className="grid justify-items-start gap-3 rounded-lg border border-dashed border-border p-6">
                            <p className="text-sm text-muted-foreground">
                                Tidak ada pesanan yang sedang berjalan.
                            </p>
                            <Link
                                href={home()}
                                className={buttonVariants({
                                    variant: 'outline',
                                    size: 'sm',
                                })}
                            >
                                Mulai belanja
                            </Link>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Ringkasan',
            href: dashboard(),
        },
    ],
};
