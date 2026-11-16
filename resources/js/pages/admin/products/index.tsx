import { Form, Head, Link, router } from '@inertiajs/react';
import { ImageOff, Search } from 'lucide-react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SimplePagination } from '@/components/simple-pagination';
import { Button, buttonVariants } from '@/components/ui/button';
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
import { formatDate } from '@/lib/date';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/products';
import type { Paginated } from '@/types/pagination';

type ProductStatus = 'draft' | 'active' | 'archived' | 'banned';

type AdminProduct = {
    id: number;
    name: string;
    cover_url: string | null;
    store: string;
    category: string;
    min_price: number;
    max_price: number;
    created_at: string | null;
    ban_reason: string | null;
    banned_at: string | null;
};

type Props = {
    products: Paginated<AdminProduct>;
    status: ProductStatus;
    search: string;
    tabs: { value: ProductStatus; label: string; count: number }[];
};

function priceRange(p: AdminProduct): string {
    return p.min_price === p.max_price
        ? formatRupiah(p.min_price)
        : `${formatRupiah(p.min_price)} – ${formatRupiah(p.max_price)}`;
}

export default function ProductsIndex({
    products,
    status,
    search,
    tabs,
}: Props) {
    const [banning, setBanning] = useState<AdminProduct | null>(null);
    const [query, setQuery] = useState(search);

    return (
        <>
            <Head title="Produk" />

            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <Heading
                    title="Produk"
                    description="Blokir produk yang melanggar aturan. Penjual akan melihat alasan Anda."
                />

                <form
                    role="search"
                    className="relative"
                    onSubmit={(e) => {
                        e.preventDefault();
                        // GET with the query in the URL: shareable, and the
                        // back button returns to the same results.
                        router.get(
                            index.url({
                                query: { status, q: query.trim() || undefined },
                            }),
                            {},
                            { preserveState: true },
                        );
                    }}
                >
                    <Label htmlFor="product-search" className="sr-only">
                        Cari produk
                    </Label>
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <Input
                        id="product-search"
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Cari nama produk atau toko, lalu Enter"
                        className="pl-9"
                    />
                </form>

                <nav
                    aria-label="Status produk"
                    className="-mb-2 flex gap-1 overflow-x-auto border-b border-border"
                >
                    {tabs.map((tab) => {
                        const current = tab.value === status;

                        return (
                            <Link
                                key={tab.value}
                                href={index({
                                    query: {
                                        status: tab.value,
                                        q: search || undefined,
                                    },
                                })}
                                preserveScroll
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm whitespace-nowrap',
                                    current
                                        ? 'border-primary font-semibold text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.label}
                                <span className="rounded-full bg-muted px-1.5 text-xs text-muted-foreground tabular-nums">
                                    {tab.count}
                                </span>
                            </Link>
                        );
                    })}
                </nav>

                {products.data.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-border px-6 py-14 text-center text-sm text-muted-foreground">
                        {search
                            ? `Tidak ada produk yang cocok dengan "${search}".`
                            : 'Tidak ada produk di sini.'}
                    </p>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border bg-card">
                        {products.data.map((p) => (
                            <li
                                key={p.id}
                                className="flex flex-wrap items-start gap-x-4 gap-y-3 p-4"
                            >
                                <div className="size-14 shrink-0 overflow-hidden rounded-md bg-muted">
                                    {p.cover_url ? (
                                        <img
                                            src={p.cover_url}
                                            alt=""
                                            loading="lazy"
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <span className="grid size-full place-items-center text-muted-foreground">
                                            <ImageOff
                                                className="size-5"
                                                aria-label="Tanpa foto"
                                            />
                                        </span>
                                    )}
                                </div>

                                <div className="grid min-w-0 flex-1 basis-56 gap-1">
                                    <p className="font-semibold">{p.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        <span className="text-foreground">
                                            {p.store}
                                        </span>{' '}
                                        · {p.category} ·{' '}
                                        <span className="tabular-nums">
                                            {priceRange(p)}
                                        </span>
                                    </p>
                                    {p.ban_reason && (
                                        <p className="mt-1 text-sm">
                                            <span className="text-muted-foreground">
                                                Diblokir{' '}
                                                {formatDate(p.banned_at)}:
                                            </span>{' '}
                                            {p.ban_reason}
                                        </p>
                                    )}
                                </div>

                                {status === 'banned' ? (
                                    <Link
                                        href={ProductController.unban(p.id)}
                                        as="button"
                                        preserveScroll
                                        className={buttonVariants({
                                            size: 'sm',
                                            variant: 'outline',
                                        })}
                                    >
                                        Buka blokir
                                    </Link>
                                ) : (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setBanning(p)}
                                    >
                                        Blokir
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <SimplePagination page={products} />
            </div>

            <BanDialog product={banning} onClose={() => setBanning(null)} />
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Produk', href: index() }],
};

function BanDialog({
    product,
    onClose,
}: {
    product: AdminProduct | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={product !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {product && (
                    <Form
                        {...ProductController.ban.form(product.id)}
                        options={{ preserveScroll: true }}
                        // Close only when it worked; on a validation error
                        // the dialog stays open to show the message.
                        onSuccess={onClose}
                        className="grid gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1.5">
                                    <DialogTitle>
                                        Blokir "{product.name}"?
                                    </DialogTitle>
                                    <DialogDescription>
                                        Produk langsung hilang dari toko dan
                                        penjual tidak bisa mengubahnya sampai
                                        blokir dibuka.
                                    </DialogDescription>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ban-reason">
                                        Alasan untuk penjual
                                    </Label>
                                    <textarea
                                        id="ban-reason"
                                        name="reason"
                                        rows={3}
                                        required
                                        minLength={10}
                                        maxLength={500}
                                        aria-invalid={!!errors.reason}
                                        placeholder="Contoh: Foto produk memakai logo merek lain tanpa izin."
                                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm"
                                    />
                                    <InputError message={errors.reason} />
                                </div>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Batal
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        Blokir produk
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
