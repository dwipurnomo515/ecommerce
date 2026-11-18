import { Form, Head, Link } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import SellerReviewController from '@/actions/App/Http/Controllers/Seller/ReviewController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { RatingSummary } from '@/components/shop/rating-summary';
import type { Rating } from '@/components/shop/rating-summary';
import { Stars } from '@/components/shop/stars';
import { SimplePagination } from '@/components/simple-pagination';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/date';
import { cn } from '@/lib/utils';
import { index } from '@/routes/seller/reviews';
import type { Paginated } from '@/types/pagination';

type SellerReview = {
    id: number;
    product: string;
    variant: string | null;
    author: string;
    rating: number;
    body: string | null;
    created_at: string | null;
    seller_reply: string | null;
    replied_at: string | null;
    hidden_reason: string | null;
};

type Props = {
    reviews: Paginated<SellerReview>;
    filter: 'belum-dibalas' | 'semua';
    unansweredCount: number;
    rating: Rating | null;
};

export default function SellerReviews({
    reviews,
    filter,
    unansweredCount,
    rating,
}: Props) {
    const tabs = [
        {
            value: 'belum-dibalas',
            label: 'Belum dibalas',
            count: unansweredCount,
        },
        { value: 'semua', label: 'Semua', count: null },
    ] as const;

    return (
        <>
            <Head title="Ulasan" />

            <div className="mx-auto grid w-full max-w-3xl gap-6 p-4 md:p-6">
                <div className="grid gap-1">
                    <Heading
                        title="Ulasan"
                        description="Balasan Anda tampil di halaman produk dan tidak bisa diubah, jadi tulis dengan tenang."
                    />
                    <RatingSummary rating={rating} className="text-sm" />
                </div>

                <nav
                    aria-label="Filter ulasan"
                    className="-mb-2 flex gap-1 border-b border-border"
                >
                    {tabs.map((tab) => {
                        const current = tab.value === filter;

                        return (
                            <Link
                                key={tab.value}
                                href={index({ query: { filter: tab.value } })}
                                preserveScroll
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    '-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm',
                                    current
                                        ? 'border-primary font-semibold text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.label}
                                {tab.count !== null && (
                                    <span
                                        className={cn(
                                            'rounded-full px-1.5 text-xs tabular-nums',
                                            tab.count > 0
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground',
                                        )}
                                    >
                                        {tab.count}
                                    </span>
                                )}
                            </Link>
                        );
                    })}
                </nav>

                {reviews.data.length === 0 ? (
                    <div className="grid justify-items-center gap-2 rounded-lg border border-dashed border-border px-6 py-14 text-center">
                        <MessageSquare
                            className="size-6 text-muted-foreground"
                            aria-hidden
                        />
                        <p className="text-sm text-muted-foreground">
                            {filter === 'belum-dibalas'
                                ? 'Semua ulasan sudah dibalas.'
                                : 'Belum ada ulasan untuk produk Anda.'}
                        </p>
                    </div>
                ) : (
                    <ul className="grid gap-4">
                        {reviews.data.map((r) => (
                            <li
                                key={r.id}
                                className="grid gap-3 rounded-lg border border-border bg-card p-4 text-sm"
                            >
                                <div className="grid gap-1">
                                    <p className="font-medium">
                                        {r.product}
                                        {r.variant && (
                                            <span className="font-normal text-muted-foreground">
                                                {' '}
                                                · {r.variant}
                                            </span>
                                        )}
                                    </p>
                                    <p className="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                        <Stars value={r.rating} />
                                        {r.author} · {formatDate(r.created_at)}
                                    </p>
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

                                {r.hidden_reason ? (
                                    <p className="rounded-md border border-border p-3 text-muted-foreground">
                                        <span className="font-medium text-foreground">
                                            Disembunyikan admin:
                                        </span>{' '}
                                        {r.hidden_reason}. Ulasan ini tidak
                                        tampil dan tidak dihitung di rating.
                                    </p>
                                ) : r.seller_reply ? (
                                    <p className="rounded-md bg-muted/60 p-3 text-muted-foreground">
                                        <span className="font-medium text-foreground">
                                            Balasan Anda ·{' '}
                                            {formatDate(r.replied_at)}:
                                        </span>{' '}
                                        {r.seller_reply}
                                    </p>
                                ) : (
                                    <Form
                                        {...SellerReviewController.reply.form(
                                            r.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                        resetOnSuccess
                                        className="grid gap-2"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <label
                                                    htmlFor={`reply-${r.id}`}
                                                    className="sr-only"
                                                >
                                                    Balas ulasan dari {r.author}
                                                </label>
                                                <textarea
                                                    id={`reply-${r.id}`}
                                                    name="reply"
                                                    rows={2}
                                                    required
                                                    minLength={2}
                                                    maxLength={1000}
                                                    aria-invalid={
                                                        !!errors.reply
                                                    }
                                                    placeholder="Tulis balasan untuk pembeli…"
                                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm"
                                                />
                                                <InputError
                                                    message={errors.reply}
                                                />
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={processing}
                                                    className="justify-self-start"
                                                >
                                                    Kirim balasan
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <SimplePagination page={reviews} />
            </div>
        </>
    );
}

SellerReviews.layout = {
    breadcrumbs: [{ title: 'Ulasan', href: index() }],
};
