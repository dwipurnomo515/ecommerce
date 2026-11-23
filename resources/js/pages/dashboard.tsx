import { Head, Link } from '@inertiajs/react';
import {
    Banknote,
    ChevronRight,
    CreditCard,
    Inbox,
    MessageSquare,
    MessageSquareWarning,
    PackageCheck,
    PackageX,
    Store,
    Truck,
    Undo2,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { buttonVariants } from '@/components/ui/button';
import { formatDate } from '@/lib/date';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import { dashboard, home } from '@/routes';
import { index as adminFinance } from '@/routes/admin/finance';
import { index as adminReviews } from '@/routes/admin/reviews';
import { index as adminStores } from '@/routes/admin/stores';
import { placed } from '@/routes/checkout';
import { index as myOrders } from '@/routes/orders';
import { index as sellerBalance } from '@/routes/seller/balance';
import { index as sellerOrders } from '@/routes/seller/orders';
import {
    edit as editProduct,
    index as sellerProducts,
} from '@/routes/seller/products';
import { index as sellerReviews } from '@/routes/seller/reviews';
import { show as sellerStore } from '@/routes/seller/store';

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

type SellerSummary = {
    store: {
        name: string;
        status: 'pending' | 'active' | 'suspended';
        status_label: string;
    };
    counts: {
        to_process: number;
        to_ship: number;
        unanswered_reviews: number;
        low_stock: number;
    };
    balance: { available: number; held: number; can_withdraw: boolean };
    low_stock: {
        id: number;
        product_id: number;
        product: string;
        variant: string;
        stock: number;
    }[];
};

function Stat({
    icon: Icon,
    label,
    value,
    hint,
    href = myOrders(),
    alert = false,
}: {
    icon: LucideIcon;
    label: string;
    value: number;
    hint: string;
    href?: ComponentProps<typeof Link>['href'];
    alert?: boolean;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'grid gap-1 rounded-lg border p-4 hover:bg-muted/40',
                alert && value > 0 ? 'border-warning/50' : 'border-border',
            )}
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

function SellerSection({ seller }: { seller: SellerSummary }) {
    const { store, counts, balance } = seller;

    return (
        <section className="grid gap-4" aria-labelledby="toko">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="toko" className="text-xl font-semibold tracking-tight">
                    Toko {store.name}
                </h2>
                <Link
                    href={sellerStore()}
                    className="text-sm text-muted-foreground hover:underline"
                >
                    Profil toko
                </Link>
            </div>

            {store.status !== 'active' && (
                <p className="rounded-lg border border-warning/40 p-4 text-sm">
                    <span className="font-medium">{store.status_label}.</span>{' '}
                    {store.status === 'pending'
                        ? 'Produkmu baru tampil untuk pembeli setelah toko disetujui admin.'
                        : 'Produkmu tidak tampil untuk pembeli sampai toko diaktifkan kembali.'}
                </p>
            )}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    icon={Inbox}
                    label="Perlu diproses"
                    value={counts.to_process}
                    hint="Sudah dibayar pembeli"
                    href={sellerOrders({ query: { status: 'paid' } })}
                    alert
                />
                <Stat
                    icon={Truck}
                    label="Siap dikirim"
                    value={counts.to_ship}
                    hint="Isi nomor resi"
                    href={sellerOrders({ query: { status: 'processing' } })}
                    alert
                />
                <Stat
                    icon={MessageSquare}
                    label="Ulasan belum dibalas"
                    value={counts.unanswered_reviews}
                    hint="Balas agar pembeli yakin"
                    href={sellerReviews()}
                />
                <Stat
                    icon={PackageX}
                    label="Stok menipis"
                    value={counts.low_stock}
                    hint="Varian aktif, stok ≤ 5"
                    href={sellerProducts()}
                    alert
                />
            </div>

            <Link
                href={sellerBalance()}
                className="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-border p-4 hover:bg-muted/40"
            >
                <Wallet className="size-5 text-muted-foreground" aria-hidden />
                <span className="grid gap-0.5">
                    <span className="text-xs text-muted-foreground">
                        Saldo bisa dicairkan
                    </span>
                    <span className="text-lg font-semibold tabular-nums">
                        {formatRupiah(balance.available)}
                    </span>
                </span>
                <span className="grid gap-0.5">
                    <span className="text-xs text-muted-foreground">
                        Masih ditahan (pesanan belum selesai)
                    </span>
                    <span className="text-lg font-semibold text-muted-foreground tabular-nums">
                        {formatRupiah(balance.held)}
                    </span>
                </span>
                <span className="ml-auto text-sm font-medium">
                    {balance.can_withdraw ? 'Cairkan' : 'Lihat mutasi'}
                </span>
            </Link>

            {seller.low_stock.length > 0 && (
                <div className="grid gap-2">
                    <h3 className="text-sm font-medium text-muted-foreground">
                        Stok menipis
                    </h3>
                    <ul className="divide-y divide-border rounded-lg border border-border">
                        {seller.low_stock.map((v) => (
                            <li key={v.id}>
                                <Link
                                    href={editProduct(v.product_id)}
                                    className="flex items-center gap-4 p-4 hover:bg-muted/40"
                                >
                                    <div className="grid min-w-0 flex-1 gap-0.5">
                                        <p className="truncate text-sm font-medium">
                                            {v.product}
                                        </p>
                                        {v.variant !== '' && (
                                            <p className="text-xs text-muted-foreground">
                                                {v.variant}
                                            </p>
                                        )}
                                    </div>
                                    <span
                                        className={cn(
                                            'text-xs font-medium tabular-nums',
                                            v.stock === 0
                                                ? 'text-destructive'
                                                : 'text-warning',
                                        )}
                                    >
                                        {v.stock === 0
                                            ? 'Habis'
                                            : `Sisa ${v.stock}`}
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
        </section>
    );
}

type AdminSummary = {
    counts: {
        pending_stores: number;
        low_reviews: number;
        payouts: number;
        refunds: number;
    };
    pending_stores: {
        id: number;
        name: string;
        owner: string;
        created_at: string;
    }[];
};

function AdminSection({ admin }: { admin: AdminSummary }) {
    const { counts } = admin;

    return (
        <section className="grid gap-4" aria-labelledby="admin">
            <h2 id="admin" className="text-xl font-semibold tracking-tight">
                Perlu ditangani admin
            </h2>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    icon={Store}
                    label="Toko menunggu"
                    value={counts.pending_stores}
                    hint="Setujui atau tolak"
                    href={adminStores()}
                    alert
                />
                <Stat
                    icon={MessageSquareWarning}
                    label="Ulasan rating rendah"
                    value={counts.low_reviews}
                    hint="Bintang 1–2, 7 hari terakhir"
                    href={adminReviews()}
                />
                <Stat
                    icon={Banknote}
                    label="Penarikan bermasalah"
                    value={counts.payouts}
                    hint="Macet atau gagal"
                    href={adminFinance()}
                    alert
                />
                <Stat
                    icon={Undo2}
                    label="Refund bermasalah"
                    value={counts.refunds}
                    hint="Pembeli masih menunggu dana"
                    href={adminFinance()}
                    alert
                />
            </div>

            {admin.pending_stores.length > 0 && (
                <div className="grid gap-2">
                    <h3 className="text-sm font-medium text-muted-foreground">
                        Toko menunggu persetujuan
                    </h3>
                    <ul className="divide-y divide-border rounded-lg border border-border">
                        {admin.pending_stores.map((s) => (
                            <li key={s.id}>
                                <Link
                                    href={adminStores()}
                                    className="flex items-center gap-4 p-4 hover:bg-muted/40"
                                >
                                    <div className="grid min-w-0 flex-1 gap-0.5">
                                        <p className="truncate text-sm font-medium">
                                            {s.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {s.owner} · daftar{' '}
                                            {formatDate(s.created_at)}
                                        </p>
                                    </div>
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
        </section>
    );
}

export default function Dashboard({
    buyer,
    seller,
    admin,
}: {
    buyer: BuyerSummary;
    seller: SellerSummary | null;
    admin: AdminSummary | null;
}) {
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

                {seller && <SellerSection seller={seller} />}

                {admin && <AdminSection admin={admin} />}
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
