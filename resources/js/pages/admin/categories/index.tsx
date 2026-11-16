import { Head } from '@inertiajs/react';

/*
 * Temporary page: the backend for category management is done, the real UI
 * (tree, create/edit dialog, delete) is the next step.
 */
export default function CategoriesIndex() {
    return (
        <>
            <Head title="Kategori" />
            <div className="p-6">
                <h1 className="text-xl font-semibold">Kategori</h1>
                <p className="text-sm text-muted-foreground">
                    Tampilan halaman ini dibuat di langkah berikutnya.
                </p>
            </div>
        </>
    );
}
