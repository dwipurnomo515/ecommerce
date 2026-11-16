import { Form, Head } from '@inertiajs/react';
import { CircleCheck, Clock, OctagonAlert } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import StoreController from '@/actions/App/Http/Controllers/Seller/StoreController';
import Heading from '@/components/heading';
import { StoreProfileFields } from '@/components/seller/store-profile-fields';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/date';
import { cn } from '@/lib/utils';
import { show } from '@/routes/seller/store';

type Status = 'pending' | 'active' | 'suspended';

type Props = {
    store: {
        name: string;
        slug: string;
        description: string | null;
        status: Status;
        status_label: string;
        approved_at: string | null;
    };
};

const notices: Record<
    Status,
    { icon: LucideIcon; title: string; body: string; tone: string }
> = {
    pending: {
        icon: Clock,
        title: 'Toko Anda sedang ditinjau',
        body: 'Admin Zetago akan memeriksa profil toko Anda. Selama menunggu, Anda masih bisa memperbaiki nama, alamat, dan deskripsi toko.',
        tone: 'border-warning/30 text-warning',
    },
    active: {
        icon: CircleCheck,
        title: 'Toko Anda aktif',
        body: 'Toko Anda sudah disetujui dan bisa mulai berjualan.',
        tone: 'border-success/30 text-success',
    },
    suspended: {
        icon: OctagonAlert,
        title: 'Toko Anda ditangguhkan',
        body: 'Toko dan produk Anda sedang tidak tampil ke pembeli. Hubungi admin Zetago untuk informasi lebih lanjut.',
        tone: 'border-destructive/30 text-destructive',
    },
};

export default function ShowStore({ store }: Props) {
    const notice = notices[store.status];
    const Icon = notice.icon;

    return (
        <>
            <Head title="Toko saya" />

            <div className="mx-auto grid w-full max-w-xl gap-8 p-4 md:p-6">
                <Heading title={store.name} description="Profil toko Anda" />

                <div
                    role="status"
                    className={cn(
                        'flex gap-3 rounded-lg border p-4',
                        notice.tone,
                    )}
                >
                    <Icon className="mt-0.5 size-5 shrink-0" aria-hidden />
                    <div className="grid gap-1">
                        <p className="font-semibold">{notice.title}</p>
                        <p className="text-sm text-muted-foreground">
                            {notice.body}
                        </p>
                        {store.approved_at && (
                            <p className="text-xs text-muted-foreground tabular-nums">
                                Disetujui {formatDate(store.approved_at)}
                            </p>
                        )}
                    </div>
                </div>

                <Form
                    {...StoreController.update.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <StoreProfileFields
                                errors={errors}
                                defaults={store}
                                slugEditable={store.status === 'pending'}
                            />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="justify-self-start"
                            >
                                Simpan profil
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ShowStore.layout = {
    breadcrumbs: [{ title: 'Toko saya', href: show() }],
};
