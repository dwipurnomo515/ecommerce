import { Head } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import Heading from '@/components/heading';
import { ProductForm } from '@/components/seller/product-form';
import type {
    CategoryChoice,
    EditableProduct,
    FormLimits,
} from '@/components/seller/product-form';
import { ProductTabs } from '@/components/seller/product-tabs';
import { buttonVariants } from '@/components/ui/button';
import { index } from '@/routes/seller/products';

type Props = {
    categories: CategoryChoice[];
    limits: FormLimits;
    product: EditableProduct;
    /** Set only while buyers can open the product. */
    publicUrl: string | null;
};

export default function EditProduct({
    categories,
    limits,
    product,
    publicUrl,
}: Props) {
    return (
        <>
            <Head title={`Edit · ${product.name}`} />

            <div className="mx-auto grid w-full max-w-3xl gap-8 p-4 pb-28 md:p-6 md:pb-28">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={product.name} />
                    {publicUrl && (
                        // A normal <a> in a new tab: the seller keeps the
                        // editor open while checking the public page.
                        <a
                            href={publicUrl}
                            target="_blank"
                            rel="noopener"
                            className={buttonVariants({ variant: 'outline' })}
                        >
                            <ExternalLink />
                            Lihat di toko
                        </a>
                    )}
                </div>
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
