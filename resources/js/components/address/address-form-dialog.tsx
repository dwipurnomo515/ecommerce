import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AddressController from '@/actions/App/Http/Controllers/Settings/AddressController';
import { DestinationPicker } from '@/components/address/destination-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type AddressRow = {
    id: number;
    label: string | null;
    recipient_name: string;
    phone: string;
    street: string;
    notes: string | null;
    region: string;
    is_primary: boolean;
};

type Props = {
    /** null = add a new address. */
    address: AddressRow | null;
    open: boolean;
    onClose: () => void;
};

export function AddressFormDialog({ address, open, onClose }: Props) {
    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                {/* Remount per address so the form starts from its values. */}
                {open && (
                    <AddressForm
                        key={address?.id ?? 'new'}
                        address={address}
                        onDone={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function AddressForm({
    address,
    onDone,
}: {
    address: AddressRow | null;
    onDone: () => void;
}) {
    const [region, setRegion] = useState<string | null>(
        address?.region ?? null,
    );

    const form = useForm({
        label: address?.label ?? '',
        recipient_name: address?.recipient_name ?? '',
        phone: address?.phone ?? '',
        street: address?.street ?? '',
        notes: address?.notes ?? '',
        destination_token: '',
        is_primary: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit(
            address
                ? AddressController.update(address.id)
                : AddressController.store(),
            { preserveScroll: true, onSuccess: onDone },
        );
    };

    return (
        <form onSubmit={submit} className="grid gap-5">
            <div className="grid gap-1.5">
                <DialogTitle>
                    {address ? 'Ubah alamat' : 'Alamat baru'}
                </DialogTitle>
                <DialogDescription>
                    Dipakai kurir untuk mengantar pesanan Anda.
                </DialogDescription>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    id="recipient_name"
                    label="Nama penerima"
                    error={form.errors.recipient_name}
                >
                    <Input
                        id="recipient_name"
                        value={form.data.recipient_name}
                        onChange={(e) =>
                            form.setData('recipient_name', e.target.value)
                        }
                        autoComplete="name"
                        maxLength={100}
                        required
                    />
                </Field>
                <Field id="phone" label="Nomor HP" error={form.errors.phone}>
                    <Input
                        id="phone"
                        type="tel"
                        inputMode="tel"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                        autoComplete="tel"
                        placeholder="0812 3456 7890"
                        required
                    />
                </Field>
            </div>

            <Field
                id="destination"
                label="Kecamatan / kelurahan"
                error={form.errors.destination_token}
            >
                <DestinationPicker
                    inputId="destination"
                    value={region}
                    invalid={Boolean(form.errors.destination_token)}
                    onChange={(choice) => {
                        setRegion(choice.label);
                        form.setData('destination_token', choice.token);
                    }}
                />
            </Field>

            <Field
                id="street"
                label="Alamat lengkap"
                error={form.errors.street}
            >
                <textarea
                    id="street"
                    value={form.data.street}
                    onChange={(e) => form.setData('street', e.target.value)}
                    rows={3}
                    maxLength={500}
                    required
                    autoComplete="street-address"
                    placeholder="Nama jalan, nomor rumah, RT/RW, gedung/lantai"
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                />
            </Field>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    id="notes"
                    label="Patokan (opsional)"
                    error={form.errors.notes}
                >
                    <Input
                        id="notes"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        maxLength={200}
                        placeholder="Pagar hijau, sebelah masjid"
                    />
                </Field>
                <Field
                    id="label"
                    label="Label (opsional)"
                    error={form.errors.label}
                >
                    <Input
                        id="label"
                        value={form.data.label}
                        onChange={(e) => form.setData('label', e.target.value)}
                        maxLength={30}
                        placeholder="Rumah, Kantor"
                    />
                </Field>
            </div>

            {!address?.is_primary && (
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_primary}
                        onChange={(e) =>
                            form.setData('is_primary', e.target.checked)
                        }
                        className="size-4 accent-primary"
                    />
                    Jadikan alamat utama
                </label>
            )}

            <DialogFooter className="gap-2">
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Batal
                    </Button>
                </DialogClose>
                <Button type="submit" disabled={form.processing}>
                    Simpan alamat
                </Button>
            </DialogFooter>
        </form>
    );
}

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
