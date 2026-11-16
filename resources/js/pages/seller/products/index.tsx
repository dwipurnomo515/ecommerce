import { Head } from '@inertiajs/react';

// Temporary page: the real UI is built in the next step.
export default function Placeholder() {
    return (
        <>
            <Head title="Produk" />
            <p className="p-6 text-sm text-muted-foreground">
                Tampilan halaman ini dibuat di langkah berikutnya.
            </p>
        </>
    );
}
