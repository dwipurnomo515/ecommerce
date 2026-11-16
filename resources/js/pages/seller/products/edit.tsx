import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ProductForm } from '@/components/seller/product-form';
import type {
    CategoryChoice,
    EditableProduct,
    FormLimits,
} from '@/components/seller/product-form';
import { ProductTabs } from '@/components/seller/product-tabs';
import { index } from '@/routes/seller/products';

type Props = {
    categories: CategoryChoice[];
    limits: FormLimits;
    product: EditableProduct;
};

export default function EditProduct({ categories, limits, product }: Props) {
    return (
        <>
            <Head title={`Edit · ${product.name}`} />

            <div className="mx-auto grid w-full max-w-3xl gap-8 p-4 pb-28 md:p-6 md:pb-28">
                <Heading title={product.name} />
                <ProductTabs productId={product.id} current="info" />
                {/* key: remount with fresh state if another product opens */}
                <ProductForm
                    key={product.id}
                    categories={categories}
                    limits={limits}
                    product={product}
                />
            </div>
        </>
    );
}

EditProduct.layout = {
    breadcrumbs: [{ title: 'Produk', href: index() }],
};
