import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ProductForm } from '@/components/seller/product-form';
import type {
    CategoryChoice,
    FormLimits,
} from '@/components/seller/product-form';
import { create, index } from '@/routes/seller/products';

type Props = {
    categories: CategoryChoice[];
    limits: FormLimits;
};

export default function CreateProduct({ categories, limits }: Props) {
    return (
        <>
            <Head title="Tambah produk" />

            <div className="mx-auto grid w-full max-w-3xl gap-10 p-4 pb-28 md:p-6 md:pb-28">
                <Heading
                    title="Tambah produk"
                    description="Produk yang diterbitkan langsung tampil di toko Anda. Simpan sebagai draf kalau belum siap."
                />
                <ProductForm categories={categories} limits={limits} />
            </div>
        </>
    );
}

CreateProduct.layout = {
    breadcrumbs: [
        { title: 'Produk', href: index() },
        { title: 'Tambah produk', href: create() },
    ],
};
