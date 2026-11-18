import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';

export type Rating = { average: number; count: number };

/**
 * Compact "★ 4,8 (12 ulasan)". Renders nothing without reviews: a new
 * store or product shows no stars rather than a discouraging zero.
 */
export function RatingSummary({
    rating,
    className,
}: {
    rating: Rating | null;
    className?: string;
}) {
    if (!rating) {
        return null;
    }

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 tabular-nums',
                className,
            )}
        >
            <Star className="size-3.5 fill-current text-warning" aria-hidden />
            <span className="font-semibold text-foreground">
                {rating.average.toLocaleString('id-ID', {
                    minimumFractionDigits: 1,
                })}
            </span>
            <span className="text-muted-foreground">
                ({rating.count} ulasan)
            </span>
        </span>
    );
}
