import { Form, Head, usePoll } from '@inertiajs/react';
import { FlaskConical, Loader2, Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';
import BalanceController from '@/actions/App/Http/Controllers/Seller/BalanceController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type Account = {
    bank: string;
    bank_name: string;
    number: string;
    masked: string;
    name: string;
};

type PayoutRow = {
    id: number;
    amount: number;
    status: 'pending' | 'paid' | 'failed';
    status_label: string;
    destination: string;
    reference: string | null;
    failure_reason: string | null;
    created_at: string | null;
};

type Props = {
    balance: number;
    held: number;
    commissionPercent: number;
    entries: Paginated<Entry>;
    account: Account | null;
    banks: { code: string; name: string }[];
    minimum: number;
    payouts: PayoutRow[];
    simulated: boolean;
};

export default function Balance({
    balance,
    held,
    commissionPercent,
    entries,
    account,
    banks,
    minimum,
    payouts,
    simulated,
}: Props) {
    const [editingAccount, setEditingAccount] = useState(account === null);
    const hasPending = payouts.some((p) => p.status === 'pending');

    // While a payout is in progress, check back every few seconds so the
    // seller sees it finish without reloading (the job runs in the background).
    // Started/stopped from an effect: autoStart is only read on first render,
    // and a new withdrawal makes hasPending true AFTER that.
    const { start, stop } = usePoll(
        4000,
        { only: ['balance', 'payouts', 'entries'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (hasPending) {
            start();
        } else {
            stop();
        }
    }, [hasPending, start, stop]);

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

                {simulated && (
                    <p className="flex gap-2 rounded-lg border border-warning/30 p-3 text-sm text-muted-foreground">
                        <FlaskConical
                            className="mt-0.5 size-4 shrink-0 text-warning"
                            aria-hidden
                        />
                        <span>
                            <span className="font-semibold text-foreground">
                                Mode simulasi:
                            </span>{' '}
                            penarikan diproses otomatis tanpa transfer uang
                            sungguhan. Nomor rekening berakhiran <b>0000</b>{' '}
                            akan gagal (dana kembali ke saldo), berakhiran{' '}
                            <b>9999</b> meniru gangguan bank (dicoba ulang
                            otomatis).
                        </span>
                    </p>
                )}

                <section className="grid gap-4 rounded-lg border border-border p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="grid gap-0.5">
                            <h2 className="font-semibold">
                                Rekening penarikan
                            </h2>
                            {account && !editingAccount && (
                                <p className="text-sm text-muted-foreground">
                                    {account.bank_name} {account.masked} · a.n.{' '}
                                    {account.name}
                                </p>
                            )}
                        </div>
                        {account && !editingAccount && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setEditingAccount(true)}
                            >
                                Ubah
                            </Button>
                        )}
                    </div>

                    {editingAccount && (
                        <Form
                            {...BalanceController.updateAccount.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditingAccount(false)}
                            className="grid gap-4 sm:grid-cols-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="bank">Bank</Label>
                                        <select
                                            id="bank"
                                            name="bank"
                                            defaultValue={account?.bank ?? ''}
                                            required
                                            className="h-9 rounded-md border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="" disabled>
                                                Pilih bank
                                            </option>
                                            {banks.map((b) => (
                                                <option
                                                    key={b.code}
                                                    value={b.code}
                                                >
                                                    {b.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.bank} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="account_number">
                                            Nomor rekening
                                        </Label>
                                        <Input
                                            id="account_number"
                                            name="account_number"
                                            inputMode="numeric"
                                            defaultValue={account?.number ?? ''}
                                            required
                                            autoComplete="off"
                                        />
                                        <InputError
                                            message={errors.account_number}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="account_name">
                                            Nama pemilik
                                        </Label>
                                        <Input
                                            id="account_name"
                                            name="account_name"
                                            defaultValue={account?.name ?? ''}
                                            required
                                        />
                                        <InputError
                                            message={errors.account_name}
                                        />
                                    </div>
                                    <div className="flex gap-2 sm:col-span-3">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Simpan rekening
                                        </Button>
                                        {account && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditingAccount(false)
                                                }
                                            >
                                                Batal
                                            </Button>
                                        )}
                                    </div>
                                </>
                            )}
                        </Form>
                    )}

                    {account && !editingAccount && (
                        <Form
                            {...BalanceController.withdraw.form()}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="grid gap-2 border-t border-border pt-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Label htmlFor="amount">Tarik dana</Label>
                                    <div className="flex flex-wrap gap-2">
                                        <Input
                                            id="amount"
                                            name="amount"
                                            type="number"
                                            inputMode="numeric"
                                            min={minimum}
                                            max={balance}
                                            step={1}
                                            required
                                            placeholder={`Minimal ${formatRupiah(minimum)}`}
                                            className="max-w-56"
                                            disabled={hasPending}
                                        />
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                hasPending ||
                                                balance < minimum
                                            }
                                        >
                                            Tarik ke {account.bank_name}
                                        </Button>
                                    </div>
                                    <InputError message={errors.amount} />
                                    {hasPending && (
                                        <p className="text-xs text-muted-foreground">
                                            Tunggu penarikan sebelumnya selesai.
                                        </p>
                                    )}
                                </>
                            )}
                        </Form>
                    )}

                    {payouts.length > 0 && (
                        <ul className="grid gap-2 border-t border-border pt-4 text-sm">
                            {payouts.map((p) => (
                                <li
                                    key={p.id}
                                    className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1"
                                >
                                    <span className="grid gap-0.5">
                                        <span className="tabular-nums">
                                            {formatRupiah(p.amount)} →{' '}
                                            {p.destination}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {formatDate(p.created_at)}
                                            {p.reference &&
                                                ` · Ref ${p.reference}`}
                                            {p.failure_reason &&
                                                ` · ${p.failure_reason}`}
                                        </span>
                                    </span>
                                    <span
                                        className={cn(
                                            'inline-flex items-center gap-1.5 text-xs font-medium',
                                            p.status === 'paid' &&
                                                'text-success',
                                            p.status === 'failed' &&
                                                'text-destructive',
                                            p.status === 'pending' &&
                                                'text-warning',
                                        )}
                                    >
                                        {p.status === 'pending' && (
                                            <Loader2
                                                className="size-3 animate-spin"
                                                aria-hidden
                                            />
                                        )}
                                        {p.status_label}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

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
