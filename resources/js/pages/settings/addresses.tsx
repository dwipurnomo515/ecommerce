import { Head, Link, router } from '@inertiajs/react';
import { MapPin, Plus } from 'lucide-react';
import { useState } from 'react';
import AddressController from '@/actions/App/Http/Controllers/Settings/AddressController';
import { AddressFormDialog } from '@/components/address/address-form-dialog';
import type { AddressRow } from '@/components/address/address-form-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { index } from '@/routes/addresses';

type Props = {
    addresses: AddressRow[];
    maxAddresses: number;
};

export default function Addresses({ addresses, maxAddresses }: Props) {
    // undefined = closed, null = new address, row = editing it
    const [editing, setEditing] = useState<AddressRow | null | undefined>();
    const [deleting, setDeleting] = useState<AddressRow | null>(null);
    const full = addresses.length >= maxAddresses;

    return (
        <>
            <Head title="Alamat" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Alamat pengiriman"
                        description="Alamat utama dipilih otomatis saat checkout."
                    />
                    <Button
                        variant="outline"
                        onClick={() => setEditing(null)}
                        disabled={full}
                        title={
                            full ? `Maksimal ${maxAddresses} alamat` : undefined
                        }
                    >
                        <Plus />
                        Tambah alamat
                    </Button>
                </div>

                {addresses.length === 0 ? (
                    <div className="grid justify-items-center gap-2 rounded-lg border border-dashed border-border px-6 py-12 text-center">
                        <MapPin
                            className="size-6 text-muted-foreground"
                            aria-hidden
                        />
                        <p className="font-medium">Belum ada alamat</p>
                        <p className="max-w-xs text-sm text-muted-foreground">
                            Tambahkan alamat supaya ongkir bisa dihitung saat
                            checkout.
                        </p>
                    </div>
                ) : (
                    <ul className="grid gap-3">
                        {addresses.map((a) => (
                            <li
                                key={a.id}
                                className={
                                    a.is_primary
                                        ? 'grid gap-3 rounded-lg border border-primary p-4'
                                        : 'grid gap-3 rounded-lg border border-border p-4'
                                }
                            >
                                <div className="grid gap-1 text-sm">
                                    <p className="flex flex-wrap items-center gap-2">
                                        <span className="font-semibold">
                                            {a.recipient_name}
                                        </span>
                                        <span className="text-muted-foreground tabular-nums">
                                            {a.phone}
                                        </span>
                                        {a.label && (
                                            <span className="text-muted-foreground">
                                                · {a.label}
                                            </span>
                                        )}
                                        {a.is_primary && (
                                            <span className="rounded-md bg-primary-soft px-1.5 py-0.5 text-xs font-semibold text-primary">
                                                Utama
                                            </span>
                                        )}
                                    </p>
                                    <p>{a.street}</p>
                                    <p className="text-muted-foreground">
                                        {a.region}
                                    </p>
                                    {a.notes && (
                                        <p className="text-muted-foreground">
                                            Patokan: {a.notes}
                                        </p>
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setEditing(a)}
                                    >
                                        Ubah
                                    </Button>
                                    {!a.is_primary && (
                                        <Link
                                            href={AddressController.makePrimary(
                                                a.id,
                                            )}
                                            as="button"
                                            preserveScroll
                                            className="h-8 rounded-md px-3 text-sm font-medium hover:bg-muted"
                                        >
                                            Jadikan utama
                                        </Link>
                                    )}
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setDeleting(a)}
                                        className="text-muted-foreground hover:text-destructive"
                                    >
                                        Hapus
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <AddressFormDialog
                address={editing ?? null}
                open={editing !== undefined}
                onClose={() => setEditing(undefined)}
            />

            <Dialog
                open={deleting !== null}
                onOpenChange={(o) => !o && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Hapus alamat ini?</DialogTitle>
                    <DialogDescription>
                        {deleting?.is_primary
                            ? 'Ini alamat utama Anda. Alamat terbaru lainnya akan menjadi alamat utama.'
                            : 'Pesanan yang sudah dibuat tidak terpengaruh.'}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">Batal</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                deleting &&
                                router.delete(
                                    AddressController.destroy.url(deleting.id),
                                    {
                                        preserveScroll: true,
                                        onFinish: () => setDeleting(null),
                                    },
                                )
                            }
                        >
                            Hapus alamat
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Addresses.layout = {
    breadcrumbs: [{ title: 'Alamat', href: index() }],
};
