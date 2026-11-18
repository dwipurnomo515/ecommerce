import { useForm } from '@inertiajs/react';
import ReviewController from '@/actions/App/Http/Controllers/Shop/ReviewController';
import InputError from '@/components/input-error';
import { StarPicker } from '@/components/shop/stars';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';

export type ReviewTarget = { id: number; name: string; variant: string | null };

export function ReviewDialog({
    item,
    onClose,
}: {
    item: ReviewTarget | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={item !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {/* Remount per item: a fresh, empty form each time. */}
                {item && (
                    <ReviewForm key={item.id} item={item} onDone={onClose} />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ReviewForm({
    item,
    onDone,
}: {
    item: ReviewTarget;
    onDone: () => void;
}) {
    const form = useForm({ rating: 0, body: '' });
    // "review" = business rule errors (already reviewed, too late...).
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <form
            className="grid gap-5"
            onSubmit={(e) => {
                e.preventDefault();
                form.submit(ReviewController.store(item.id), {
                    preserveScroll: true,
                    onSuccess: onDone,
                });
            }}
        >
            <div className="grid gap-1.5">
                <DialogTitle>Ulas barang ini</DialogTitle>
                <DialogDescription>
                    {item.name}
                    {item.variant && ` · ${item.variant}`}
                </DialogDescription>
            </div>

            <div className="grid gap-1">
                <StarPicker
                    value={form.data.rating}
                    onChange={(n) => form.setData('rating', n)}
                />
                <InputError message={errors.rating} />
            </div>

            <div className="grid gap-2">
                <label htmlFor="review-body" className="text-sm font-medium">
                    Ceritakan pengalaman Anda{' '}
                    <span className="font-normal text-muted-foreground">
                        (opsional)
                    </span>
                </label>
                <textarea
                    id="review-body"
                    rows={4}
                    maxLength={2000}
                    value={form.data.body}
                    onChange={(e) => form.setData('body', e.target.value)}
                    placeholder="Kualitas barang, sesuai foto atau tidak, ukurannya pas?"
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                />
                <InputError message={errors.body ?? errors.review} />
            </div>

            <DialogFooter className="gap-2">
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Batal
                    </Button>
                </DialogClose>
                <Button
                    type="submit"
                    disabled={form.data.rating === 0 || form.processing}
                >
                    Kirim ulasan
                </Button>
            </DialogFooter>
        </form>
    );
}
