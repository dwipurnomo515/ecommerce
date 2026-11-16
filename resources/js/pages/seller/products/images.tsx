import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ImagePlus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import ProductImageController from '@/actions/App/Http/Controllers/Seller/ProductImageController';
import Heading from '@/components/heading';
import { ProductTabs } from '@/components/seller/product-tabs';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { index as productsIndex } from '@/routes/seller/products';

type Image = { id: number; url: string };

type Props = {
    product: { id: number; name: string };
    images: Image[];
    maxImages: number;
};

const ACCEPT = 'image/jpeg,image/png,image/webp';
const MAX_BYTES = 5 * 1024 * 1024;

export default function ProductImages({ product, images, maxImages }: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [deleting, setDeleting] = useState<Image | null>(null);
    const [clientError, setClientError] = useState<string | null>(null);

    const upload = useForm<{ images: File[] }>({ images: [] });
    const left = maxImages - images.length;

    // Server errors look like "images" or "images.2"; show them all.
    const serverErrors = [
        ...new Set(Object.values(upload.errors as Record<string, string>)),
    ];

    const send = (files: File[]) => {
        setClientError(null);

        // Quick checks in the browser save a slow upload that would fail
        // anyway. The server re-checks everything (it cannot trust us).
        if (files.length > left) {
            setClientError(`Anda hanya bisa menambah ${left} foto lagi.`);

            return;
        }

        const tooBig = files.find((f) => f.size > MAX_BYTES);

        if (tooBig) {
            setClientError(`"${tooBig.name}" lebih dari 5 MB.`);

            return;
        }

        upload.transform(() => ({ images: files }));
        upload.submit(ProductImageController.store(product.id), {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    const move = (from: number, to: number) => {
        const order = images.map((i) => i.id);
        const [moved] = order.splice(from, 1);
        order.splice(to, 0, moved);

        router.put(
            ProductImageController.reorder.url(product.id),
            { order },
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title={`Foto · ${product.name}`} />

            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={product.name} />
                    <Link
                        href={productsIndex()}
                        className={buttonVariants({ variant: 'outline' })}
                    >
                        Selesai
                    </Link>
                </div>
                <ProductTabs productId={product.id} current="photos" />
                <p className="text-sm text-muted-foreground">
                    {images.length} dari {maxImages} foto. Foto pertama menjadi
                    sampul.
                </p>

                {left > 0 && (
                    <label
                        htmlFor="product-images"
                        onDragOver={(e) => {
                            e.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={(e) => {
                            e.preventDefault();
                            setDragging(false);
                            send([...e.dataTransfer.files]);
                        }}
                        className={cn(
                            'grid cursor-pointer justify-items-center gap-2 rounded-lg border border-dashed border-border px-6 py-10 text-center transition-colors focus-within:ring-[3px] focus-within:ring-ring/50 hover:bg-muted/50',
                            dragging && 'border-primary bg-primary-soft',
                            upload.processing &&
                                'pointer-events-none opacity-60',
                        )}
                    >
                        <ImagePlus
                            className="size-7 text-muted-foreground"
                            aria-hidden
                        />
                        <span className="font-medium">
                            {upload.processing
                                ? `Mengunggah… ${upload.progress?.percentage ?? 0}%`
                                : 'Pilih foto atau tarik ke sini'}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            JPG, PNG, atau WebP · minimal 400×400 piksel ·
                            maksimal 5 MB per foto · masih bisa {left} foto
                        </span>
                        <input
                            ref={fileInput}
                            id="product-images"
                            type="file"
                            accept={ACCEPT}
                            multiple
                            className="sr-only"
                            onChange={(e) => send([...(e.target.files ?? [])])}
                        />
                    </label>
                )}

                {(clientError || serverErrors.length > 0) && (
                    <ul
                        role="alert"
                        className="grid gap-1 text-sm text-destructive"
                    >
                        {clientError && <li>{clientError}</li>}
                        {serverErrors.map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                )}

                {images.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada foto. Produk dengan foto jauh lebih mudah
                        dilirik pembeli.
                    </p>
                ) : (
                    <ol className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                        {images.map((image, i) => (
                            <li key={image.id} className="grid gap-2">
                                <div className="relative aspect-square overflow-hidden rounded-lg bg-muted">
                                    <img
                                        src={image.url}
                                        alt={`${product.name}, foto ${i + 1}`}
                                        className="size-full object-cover"
                                    />
                                    {i === 0 && (
                                        <span className="absolute top-2 left-2 rounded-md bg-background px-1.5 py-0.5 text-xs font-semibold">
                                            Sampul
                                        </span>
                                    )}
                                </div>
                                <div className="flex items-center justify-between">
                                    <div className="flex">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            disabled={i === 0}
                                            onClick={() => move(i, i - 1)}
                                            aria-label={`Pindahkan foto ${i + 1} ke kiri`}
                                        >
                                            <ArrowLeft />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            disabled={i === images.length - 1}
                                            onClick={() => move(i, i + 1)}
                                            aria-label={`Pindahkan foto ${i + 1} ke kanan`}
                                        >
                                            <ArrowRight />
                                        </Button>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => setDeleting(image)}
                                        aria-label={`Hapus foto ${i + 1}`}
                                        className="text-muted-foreground hover:text-destructive"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
            </div>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Hapus foto ini?</DialogTitle>
                    <DialogDescription>
                        Foto yang dihapus tidak bisa dikembalikan.
                    </DialogDescription>
                    {deleting && (
                        <img
                            src={deleting.url}
                            alt=""
                            className="max-h-48 w-auto justify-self-start rounded-md"
                        />
                    )}
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">Batal</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (!deleting) return;
                                router.delete(
                                    ProductImageController.destroy.url([
                                        product.id,
                                        deleting.id,
                                    ]),
                                    {
                                        preserveScroll: true,
                                        onFinish: () => setDeleting(null),
                                    },
                                );
                            }}
                        >
                            Hapus foto
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ProductImages.layout = {
    breadcrumbs: [{ title: 'Produk', href: productsIndex() }],
};
