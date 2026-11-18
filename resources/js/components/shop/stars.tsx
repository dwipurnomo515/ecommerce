import { Star } from 'lucide-react';
import { useId } from 'react';
import { cn } from '@/lib/utils';

/** Read-only stars, e.g. ★★★★☆ for 4. Screen readers hear "4 dari 5 bintang". */
export function Stars({
    value,
    className,
}: {
    value: number;
    className?: string;
}) {
    const rounded = Math.round(value);

    return (
        <span
            className={cn('inline-flex items-center gap-0.5', className)}
            role="img"
            aria-label={`${value.toLocaleString('id-ID')} dari 5 bintang`}
        >
            {[1, 2, 3, 4, 5].map((n) => (
                <Star
                    key={n}
                    className={cn(
                        'size-3.5',
                        n <= rounded
                            ? 'fill-current text-warning'
                            : 'text-muted-foreground/40',
                    )}
                    aria-hidden
                />
            ))}
        </span>
    );
}

const words = ['', 'Buruk', 'Kurang', 'Cukup', 'Bagus', 'Sangat bagus'];

/**
 * Choosing 1-5 stars. Real radio inputs underneath: arrow keys work and
 * screen readers announce "4 bintang, Bagus".
 */
export function StarPicker({
    value,
    onChange,
    name = 'rating',
}: {
    value: number;
    onChange: (value: number) => void;
    name?: string;
}) {
    const id = useId();

    return (
        <fieldset className="grid gap-1.5">
            <legend className="sr-only">Beri bintang</legend>
            <div className="flex items-center gap-1">
                {[1, 2, 3, 4, 5].map((n) => (
                    <label
                        key={n}
                        htmlFor={`${id}-${n}`}
                        className="cursor-pointer p-0.5"
                    >
                        <input
                            id={`${id}-${n}`}
                            type="radio"
                            name={name}
                            value={n}
                            checked={value === n}
                            onChange={() => onChange(n)}
                            className="peer sr-only"
                        />
                        <Star
                            className={cn(
                                'size-8 rounded-sm transition-colors peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50',
                                n <= value
                                    ? 'fill-current text-warning'
                                    : 'text-muted-foreground/40 hover:text-warning/60',
                            )}
                            aria-hidden
                        />
                        <span className="sr-only">
                            {n} bintang, {words[n]}
                        </span>
                    </label>
                ))}
            </div>
            <p className="h-5 text-sm text-muted-foreground" aria-live="polite">
                {words[value] ?? ''}
            </p>
        </fieldset>
    );
}
