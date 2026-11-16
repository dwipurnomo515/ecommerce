import { Link } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/Seller/ProductController';
import ProductImageController from '@/actions/App/Http/Controllers/Seller/ProductImageController';
import { cn } from '@/lib/utils';

type Props = {
    productId: number;
    current: 'info' | 'photos';
};

/**
 * "Info produk | Foto": two pages of one product. Real links (not client
 * state), so each tab has its own URL and the back button works.
 */
export function ProductTabs({ productId, current }: Props) {
    const tabs = [
        {
            key: 'info',
            label: 'Info produk',
            href: ProductController.edit(productId),
        },
        {
            key: 'photos',
            label: 'Foto',
            href: ProductImageController.index(productId),
        },
    ] as const;

    return (
        <nav aria-label="Bagian produk" className="border-b border-border">
            <ul className="-mb-px flex gap-6">
                {tabs.map((tab) => (
                    <li key={tab.key}>
                        <Link
                            href={tab.href}
                            aria-current={
                                tab.key === current ? 'page' : undefined
                            }
                            className={cn(
                                'inline-block border-b-2 pb-2.5 text-sm font-medium transition-colors',
                                tab.key === current
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.label}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
