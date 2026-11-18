import { Head } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import Heading from '@/components/heading';
import { SimplePagination } from '@/components/simple-pagination';
import { formatDate } from '@/lib/date';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index } from '@/routes/seller/balance';
import type { Paginated } from '@/types/pagination';

type Entry = {
    id: number;
    type: string;
    description: string;
    amount: number;
    balance_after: number;
    created_at: string;
};

type Props = {
    balance: number;
    held: number;
    commissionPercent: number;
    entries: Paginated<Entry>;
};

export default function Balance({
    balance,
    held,
    commissionPercent,
    entries,
}: Props) {
    return (
        <>
            <Head title="Saldo" />

            <div className="mx-auto grid w-full max-w-3xl gap-8 p-4 md:p-6">
                <Heading
                    title="Saldo"
                    description={`Dana pesanan masuk ke saldo setelah pembeli menerima barang, dipotong komisi Zetago ${commissionPercent.toLocaleString('id-ID')}% dari harga barang (ongkir tidak dipotong).`}
                />

                <dl className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-1 rounded-lg border border-border p-5">
                        <dt className="text-sm text-muted-foreground">
                            Saldo tersedia
                        </dt>
                        <dd className="text-3xl font-bold tracking-tight tabular-nums">
                            {formatRupiah(balance)}
                        </dd>
                    </div>
                    <div className="grid gap-1 rounded-lg border border-border p-5">
                        <dt className="text-sm text-muted-foreground">
                            Dana tertahan
                        </dt>
                        <dd className="text-3xl font-bold tracking-tight text-muted-foreground tabular-nums">
                            {formatRupiah(held)}
                        </dd>
                        <dd className="text-xs text-muted-foreground">
                            Pesanan yang sudah dibayar tapi belum diterima
                            pembeli.
                        </dd>
                    </div>
                </dl>

                <section className="grid gap-3">
                    <h2 className="font-semibold">Riwayat</h2>
                    {entries.data.length === 0 ? (
                        <div className="grid justify-items-center gap-2 rounded-lg border border-dashed border-border px-6 py-12 text-center">
                            <Wallet
                                className="size-6 text-muted-foreground"
                                aria-hidden
                            />
                            <p className="text-sm text-muted-foreground">
                                Belum ada mutasi. Saldo bertambah saat pesanan
                                pertama selesai.
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-border rounded-lg border border-border">
                            {entries.data.map((e) => (
                                <li
                                    key={e.id}
                                    className="flex items-center justify-between gap-4 p-4 text-sm"
                                >
                                    <div className="grid min-w-0 gap-0.5">
                                        <span className="truncate">
                                            {e.description}
                                        </span>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {formatDate(e.created_at)}
                                        </span>
                                    </div>
                                    <div className="grid justify-items-end gap-0.5 tabular-nums">
                                        <span
                                            className={cn(
                                                'font-semibold',
                                                e.amount >= 0
                                                    ? 'text-success'
                                                    : 'text-foreground',
                                            )}
                                        >
                                            {e.amount >= 0 ? '+' : '−'}
                                            {formatRupiah(Math.abs(e.amount))}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            Saldo{' '}
                                            {formatRupiah(e.balance_after)}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    <SimplePagination page={entries} />
                </section>
            </div>
        </>
    );
}

Balance.layout = {
    breadcrumbs: [{ title: 'Saldo', href: index() }],
};
