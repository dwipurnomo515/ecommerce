import { Form, Head, Link } from '@inertiajs/react';
import { MessageSquareOff } from 'lucide-react';
import { useState } from 'react';
import AdminReviewController from '@/actions/App/Http/Controllers/Admin/ReviewController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Stars } from '@/components/shop/stars';
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
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/date';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/reviews';
import type { Paginated } from '@/types/pagination';

type AdminReview = {
    id: number;
    rating: number;
    body: string | null;
    author: string;
    product: string;
    store: string;
    created_at: string | null;
    hidden_reason: string | null;
    hidden: boolean;
};

type Props = {
    reviews: Paginated<AdminReview>;
    filter: string;
    filters: { value: string; label: string }[];
};

export default function AdminReviews({ reviews, filter, filters }: Props) {
    const [hiding, setHiding] = useState<AdminReview | null>(null);

    return (
        <>
            <Head title="Ulasan" />

            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <Heading
                    title="Ulasan"
                    description="Sembunyikan ulasan berisi spam, kata kasar, atau data pribadi. Ulasan yang disembunyikan tidak dihitung di rating dan bisa ditampilkan kembali."
                />

                <nav
                    aria-label="Filter ulasan"
                    className="-mb-2 flex gap-1 border-b border-border"
                >
                    {filters.map((f) => (
                        <Link
                            key={f.value}
                            href={index({ query: { filter: f.value } })}
                            preserveScroll
                            aria-current={
                                f.value === filter ? 'page' : undefined
                            }
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm',
                                f.value === filter
                                    ? 'border-primary font-semibold text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {f.label}
                        </Link>
                    ))}
                </nav>

                {reviews.data.length === 0 ? (
                    <div className="grid justify-items-center gap-2 rounded-lg border border-dashed border-border px-6 py-14 text-center">
                        <MessageSquareOff
                            className="size-6 text-muted-foreground"
                            aria-hidden
                        />
                        <p className="text-sm text-muted-foreground">
                            Tidak ada ulasan di sini.
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border bg-card">
                        {reviews.data.map((r) => (
                            <li key={r.id} className="grid gap-2 p-4 text-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="flex flex-wrap items-center gap-x-2">
                                        <Stars value={r.rating} />
                                        <span className="font-medium">
                                            {r.product}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {r.store} · oleh {r.author} ·{' '}
                                            {formatDate(r.created_at)}
                                        </span>
                                    </p>
                                    {r.hidden ? (
                                        <Link
                                            href={AdminReviewController.show(
                                                r.id,
                                            )}
                                            as="button"
                                            preserveScroll
                                            className={buttonVariants({
                                                size: 'sm',
                                                variant: 'outline',
                                            })}
                                        >
                                            Tampilkan kembali
                                        </Link>
                                    ) : (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setHiding(r)}
                                        >
                                            Sembunyikan
                                        </Button>
                                    )}
                                </div>
                                {r.body ? (
                                    <p className="leading-relaxed whitespace-pre-line">
                                        {r.body}
                                    </p>
                                ) : (
                                    <p className="text-muted-foreground italic">
                                        Tanpa komentar.
                                    </p>
                                )}
                                {r.hidden_reason && (
                                    <p className="text-xs text-destructive">
                                        Disembunyikan: {r.hidden_reason}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <SimplePagination page={reviews} />
            </div>

            <Dialog
                open={hiding !== null}
                onOpenChange={(o) => !o && setHiding(null)}
            >
                <DialogContent>
                    {hiding && (
                        <Form
                            {...AdminReviewController.hide.form(hiding.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setHiding(null)}
                            className="grid gap-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-1.5">
                                        <DialogTitle>
                                            Sembunyikan ulasan ini?
                                        </DialogTitle>
                                        <DialogDescription>
                                            Ulasan tidak tampil di halaman
                                            produk dan rating produk serta toko
                                            dihitung ulang tanpa ulasan ini.
                                            Penjual melihat alasannya.
                                        </DialogDescription>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="hide-reason">
                                            Alasan
                                        </Label>
                                        <textarea
                                            id="hide-reason"
                                            name="reason"
                                            rows={2}
                                            required
                                            minLength={5}
                                            maxLength={300}
                                            aria-invalid={!!errors.reason}
                                            placeholder="Contoh: Berisi nomor telepon pribadi."
                                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm"
                                        />
                                        <InputError message={errors.reason} />
                                    </div>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                            >
                                                Batal
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            Sembunyikan
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminReviews.layout = {
    breadcrumbs: [{ title: 'Ulasan', href: index() }],
};
