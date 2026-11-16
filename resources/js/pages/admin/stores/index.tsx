import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import StoreController from '@/actions/App/Http/Controllers/Admin/StoreController';
import Heading from '@/components/heading';
import { SimplePagination } from '@/components/simple-pagination';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDate } from '@/lib/date';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/stores';
import type { Paginated } from '@/types/pagination';

type StoreStatus = 'pending' | 'active' | 'suspended';

type AdminStore = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    products_count: number;
    owner: { name: string; email: string };
    created_at: string | null;
    approved_at: string | null;
};

type Props = {
    stores: Paginated<AdminStore>;
    status: StoreStatus;
    tabs: { value: StoreStatus; label: string; count: number }[];
};

const emptyText: Record<StoreStatus, string> = {
    pending: 'Tidak ada toko yang menunggu persetujuan. Semua sudah ditinjau.',
    active: 'Belum ada toko aktif.',
    suspended: 'Tidak ada toko yang ditangguhkan.',
};

export default function StoresIndex({ stores, status, tabs }: Props) {
    const [suspending, setSuspending] = useState<AdminStore | null>(null);

    return (
        <>
            <Head title="Toko" />

            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <Heading
                    title="Toko"
                    description="Tinjau toko baru sebelum produknya tampil ke pembeli."
                />

                <nav
                    aria-label="Status toko"
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
                                        current &&
                                            tab.value === 'pending' &&
                                            tab.count > 0
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

                {stores.data.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-border px-6 py-14 text-center text-sm text-muted-foreground">
                        {emptyText[status]}
                    </p>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border bg-card">
                        {stores.data.map((store) => (
                            <li
                                key={store.id}
                                className="flex flex-wrap items-start gap-x-6 gap-y-3 p-4"
                            >
                                <div className="grid min-w-0 flex-1 basis-64 gap-1">
                                    <p className="font-semibold">
                                        {store.name}
                                    </p>
                                    {store.description && (
                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                            {store.description}
                                        </p>
                                    )}
                                    <dl className="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                        <dt>Pemilik</dt>
                                        <dd className="min-w-0 truncate text-foreground">
                                            {store.owner.name} ·{' '}
                                            {store.owner.email}
                                        </dd>
                                        <dt>Daftar</dt>
                                        <dd className="tabular-nums">
                                            {formatDate(store.created_at)}
                                        </dd>
                                        {store.approved_at && (
                                            <>
                                                <dt>Disetujui</dt>
                                                <dd className="tabular-nums">
                                                    {formatDate(
                                                        store.approved_at,
                                                    )}
                                                </dd>
                                            </>
                                        )}
                                        <dt>Produk</dt>
                                        <dd className="tabular-nums">
                                            {store.products_count}
                                        </dd>
                                    </dl>
                                </div>

                                <div className="flex gap-2">
                                    {status !== 'active' && (
                                        // A POST link rendered as a <button>,
                                        // styled like our Button component.
                                        <Link
                                            href={StoreController.approve(
                                                store.id,
                                            )}
                                            as="button"
                                            preserveScroll
                                            className={buttonVariants({
                                                size: 'sm',
                                            })}
                                        >
                                            {status === 'pending'
                                                ? 'Setujui'
                                                : 'Aktifkan lagi'}
                                        </Link>
                                    )}
                                    {status !== 'suspended' && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setSuspending(store)}
                                        >
                                            {status === 'pending'
                                                ? 'Tolak'
                                                : 'Tangguhkan'}
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <SimplePagination page={stores} />
            </div>

            <SuspendDialog
                store={suspending}
                isPending={status === 'pending'}
                onClose={() => setSuspending(null)}
            />
        </>
    );
}

StoresIndex.layout = {
    breadcrumbs: [{ title: 'Toko', href: index() }],
};

function SuspendDialog({
    store,
    isPending,
    onClose,
}: {
    store: AdminStore | null;
    isPending: boolean;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={store !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {store && (
                    <Form
                        {...StoreController.suspend.form(store.id)}
                        options={{ preserveScroll: true }}
                        onFinish={onClose}
                        className="grid gap-5"
                    >
                        {({ processing }) => (
                            <>
                                <div className="grid gap-1.5">
                                    <DialogTitle>
                                        {isPending ? 'Tolak' : 'Tangguhkan'}{' '}
                                        toko "{store.name}"?
                                    </DialogTitle>
                                    <DialogDescription>
                                        Toko yang ditangguhkan tidak tampil ke
                                        pembeli dan tidak bisa berjualan. Anda
                                        bisa mengaktifkannya lagi kapan saja
                                        dari tab Ditangguhkan.
                                    </DialogDescription>
                                </div>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Batal
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {isPending
                                            ? 'Tolak toko'
                                            : 'Tangguhkan'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
