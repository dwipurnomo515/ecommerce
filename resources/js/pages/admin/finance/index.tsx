import { Head, Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import FinanceController from '@/actions/App/Http/Controllers/Admin/FinanceController';
import Heading from '@/components/heading';
import { buttonVariants } from '@/components/ui/button';
import { formatDate } from '@/lib/date';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/finance';

type AttentionRow = {
    id: number;
    amount: number;
    status: string;
    status_label: string;
    note: string | null;
    created_at: string | null;
    retryable: boolean;
};

type Props = {
    summary: {
        commission_month: number;
        commission_total: number;
        escrow: number;
        seller_balances: number;
        payouts_in_flight: number;
    };
    payouts: (AttentionRow & { store: string })[];
    refunds: (AttentionRow & { checkout: string })[];
};

export default function Finance({ summary, payouts, refunds }: Props) {
    const cards = [
        {
            label: 'Komisi bulan ini',
            value: summary.commission_month,
            strong: true,
        },
        { label: 'Komisi total', value: summary.commission_total },
        {
            label: 'Dana di escrow',
            value: summary.escrow,
            hint: 'Sudah dibayar, pesanan belum selesai',
        },
        {
            label: 'Saldo semua penjual',
            value: summary.seller_balances,
            hint: 'Belum ditarik',
        },
        { label: 'Penarikan diproses', value: summary.payouts_in_flight },
    ];

    return (
        <>
            <Head title="Keuangan" />

            <div className="mx-auto grid w-full max-w-5xl gap-8 p-4 md:p-6">
                <Heading
                    title="Keuangan"
                    description="Posisi uang di Zetago dan transaksi yang perlu perhatian. Transfer & refund masih simulasi."
                />

                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {cards.map((c) => (
                        <div
                            key={c.label}
                            className="grid content-start gap-1 rounded-lg border border-border p-4"
                        >
                            <dt className="text-xs text-muted-foreground">
                                {c.label}
                            </dt>
                            <dd
                                className={cn(
                                    'tabular-nums',
                                    c.strong
                                        ? 'text-2xl font-bold'
                                        : 'text-lg font-semibold',
                                )}
                            >
                                {formatRupiah(c.value)}
                            </dd>
                            {c.hint && (
                                <dd className="text-xs text-muted-foreground">
                                    {c.hint}
                                </dd>
                            )}
                        </div>
                    ))}
                </dl>

                <Attention
                    title="Penarikan yang perlu perhatian"
                    empty="Tidak ada penarikan yang macet atau gagal."
                    rows={payouts.map((p) => ({ ...p, who: p.store }))}
                    retryHref={(id) => FinanceController.retryPayout(id)}
                    failedNote="Dana sudah kembali ke saldo penjual."
                />

                <Attention
                    title="Refund yang perlu perhatian"
                    empty="Tidak ada refund yang macet atau gagal."
                    rows={refunds.map((r) => ({ ...r, who: r.checkout }))}
                    retryHref={(id) => FinanceController.retryRefund(id)}
                    failedNote="Pembeli masih menunggu dananya."
                />
            </div>
        </>
    );
}

Finance.layout = {
    breadcrumbs: [{ title: 'Keuangan', href: index() }],
};

function Attention({
    title,
    empty,
    rows,
    retryHref,
    failedNote,
}: {
    title: string;
    empty: string;
    rows: (AttentionRow & { who: string })[];
    retryHref: (id: number) => ReturnType<typeof FinanceController.retryPayout>;
    failedNote: string;
}) {
    return (
        <section className="grid gap-3">
            <h2 className="font-semibold">{title}</h2>
            {rows.length === 0 ? (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <CircleCheck className="size-4 text-success" aria-hidden />
                    {empty}
                </p>
            ) : (
                <ul className="divide-y divide-border rounded-lg border border-border">
                    {rows.map((r) => (
                        <li
                            key={r.id}
                            className="flex flex-wrap items-center justify-between gap-3 p-4 text-sm"
                        >
                            <div className="grid min-w-0 gap-0.5">
                                <p>
                                    <span className="font-semibold tabular-nums">
                                        {formatRupiah(r.amount)}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {r.who} · #{r.id}
                                    </span>
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    <span
                                        className={cn(
                                            'font-medium',
                                            r.status === 'pending'
                                                ? 'text-warning'
                                                : 'text-destructive',
                                        )}
                                    >
                                        {r.status_label}
                                    </span>{' '}
                                    sejak {formatDate(r.created_at)}
                                    {r.note && ` · ${r.note}`}
                                    {r.status === 'failed' &&
                                        ` · ${failedNote}`}
                                </p>
                            </div>
                            {r.retryable && (
                                <Link
                                    href={retryHref(r.id)}
                                    as="button"
                                    preserveScroll
                                    className={buttonVariants({
                                        size: 'sm',
                                        variant: 'outline',
                                    })}
                                >
                                    Coba lagi
                                </Link>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
