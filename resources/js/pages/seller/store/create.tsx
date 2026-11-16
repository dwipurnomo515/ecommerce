import { Form, Head } from '@inertiajs/react';
import StoreController from '@/actions/App/Http/Controllers/Seller/StoreController';
import Heading from '@/components/heading';
import { StoreProfileFields } from '@/components/seller/store-profile-fields';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/seller/store';

const steps = [
    'Isi profil toko di bawah ini.',
    'Admin Zetago meninjau toko Anda.',
    'Setelah disetujui, Anda bisa mulai menambahkan produk.',
];

export default function CreateStore() {
    return (
        <>
            <Head title="Buka toko" />

            <div className="mx-auto grid w-full max-w-xl gap-8 p-4 md:p-6">
                <Heading
                    title="Buka toko di Zetago"
                    description="Gratis. Satu akun bisa memiliki satu toko."
                />

                <ol className="grid gap-2 text-sm">
                    {steps.map((step, i) => (
                        <li key={step} className="flex gap-3">
                            <span className="grid size-6 shrink-0 place-items-center rounded-full bg-primary-soft text-xs font-semibold text-primary tabular-nums">
                                {i + 1}
                            </span>
                            <span className="pt-0.5 text-muted-foreground">
                                {step}
                            </span>
                        </li>
                    ))}
                </ol>

                <Form {...StoreController.store.form()} className="grid gap-6">
                    {({ processing, errors }) => (
                        <>
                            <StoreProfileFields errors={errors} slugEditable />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="justify-self-start"
                            >
                                Daftarkan toko
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateStore.layout = {
    breadcrumbs: [{ title: 'Buka toko', href: create() }],
};
