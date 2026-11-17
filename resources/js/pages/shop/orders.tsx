import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Package } from 'lucide-react';
import { SimplePagination } from '@/components/simple-pagination';
import { buttonVariants } from '@/components/ui/button';
import { formatDate } from '@/lib/date';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { placed } from '@/routes/checkout';
import type { Paginated } from '@/types/pagination';

type PaymentStatus = 'pending' | 'paid' | 'expired' | 'needs_refund';

type CheckoutRow = {
    code: string;
    created_at: string | null;
    payment_status: PaymentStatus;
    payment_status_label: string;
    grand_total: number;
    expires_at: string;
    stores: { name: string; items_count: number }[];
};

const statusTone: Record<PaymentStatus, string> = {
    pending: 'text-warning',
    paid: 'text-success',
    expired: 'text-muted-foreground',
    needs_refund: 'text-destructive',
};

export default function Orders({
    checkouts,
}: {
    checkouts: Paginated<CheckoutRow>;
}) {
    return (
        <>
            <Head title="Pesanan saya" />

            <div className="mx-auto grid w-full max-w-3xl gap-6 px-4 py-6 md:px-6 md:py-10">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Pesanan saya
                </h1>

                {checkouts.data.length === 0 ? (
                    <div className="grid justify-items-center gap-3 rounded-lg border border-dashed border-border px-6 py-14 text-center">
                        <Package
                            className="size-8 text-muted-foreground"
                            aria-hidden
                        />
                        <p className="font-medium">Belum ada pesanan</p>
                        <Link
                            href={home()}
                            className={buttonVariants({ variant: 'outline' })}
                        >
                            Mulai belanja
                        </Link>
                    </div>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border">
                        {checkouts.data.map((c) => (
                            <li key={c.code}>
                                <Link
                                    href={placed(c.code)}
                                    className="flex items-center gap-4 p-4 hover:bg-muted/40"
                                >
                                    <div className="grid min-w-0 flex-1 gap-1">
                                        <p className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm">
                                            <span className="font-semibold tabular-nums">
                                                {c.code}
                                            </span>
                                            <span
                                                className={cn(
                                                    'text-xs font-medium',
                                                    statusTone[
                                                        c.payment_status
                                                    ],
                                                )}
                                            >
                                                {c.payment_status_label}
                                            </span>
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {c.stores
                                                .map(
                                                    (s) =>
                                                        `${s.name} (${s.items_count} barang)`,
                                                )
                                                .join(', ')}
                                        </p>
                                        <p className="text-xs text-muted-foreground tabular-nums">
                                            {formatDate(c.created_at)}
                                            {c.payment_status === 'pending' &&
                                                ` · bayar sebelum ${formatDate(c.expires_at)}`}
                                        </p>
                                    </div>
                                    <span className="font-semibold tabular-nums">
                                        {formatRupiah(c.grand_total)}
                                    </span>
                                    <ChevronRight
                                        className="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                <SimplePagination page={checkouts} />
            </div>
        </>
    );
}
