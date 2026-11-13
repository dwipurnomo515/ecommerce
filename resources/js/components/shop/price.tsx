import { discountPercent, formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';

type PriceProps = {
    amount: number;
    /** Original price shown struck through. Ignored unless it is higher. */
    compareAt?: number | null;
    /** `sm` for product cards, `lg` for the product detail page. */
    size?: 'sm' | 'lg';
    className?: string;
};

/**
 * The single way a price is shown anywhere in Zetago, so formatting and
 * discount rules can never drift between pages.
 */
export function Price({
    amount,
    compareAt,
    size = 'sm',
    className,
}: PriceProps) {
    const discount = discountPercent(amount, compareAt);
    const onSale = discount !== null && compareAt != null;

    if (size === 'lg') {
        return (
            <div className={cn('grid gap-1 tabular-nums', className)}>
                <span
                    className={cn(
                        'text-3xl font-bold tracking-tight',
                        onSale ? 'text-sale' : 'text-foreground',
                    )}
                >
                    {formatRupiah(amount)}
                </span>
                {onSale && (
                    <span className="flex flex-wrap items-baseline gap-x-2 text-sm">
                        <s className="text-muted-foreground">
                            {formatRupiah(compareAt)}
                        </s>
                        <span className="font-semibold text-sale">
                            Hemat {formatRupiah(compareAt - amount)}
                        </span>
                    </span>
                )}
            </div>
        );
    }

    return (
        <div
            className={cn(
                'flex flex-wrap items-baseline gap-x-2 tabular-nums',
                className,
            )}
        >
            <span
                className={cn(
                    'text-base font-bold tracking-tight',
                    onSale ? 'text-sale' : 'text-foreground',
                )}
            >
                {formatRupiah(amount)}
            </span>
            {onSale && (
                <s className="text-xs text-muted-foreground">
                    {formatRupiah(compareAt)}
                </s>
            )}
        </div>
    );
}
