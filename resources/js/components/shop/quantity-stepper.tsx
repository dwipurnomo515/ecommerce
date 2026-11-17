import { Minus, Plus } from 'lucide-react';
import { useId } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    value: number;
    max: number;
    onChange: (value: number) => void;
    /** Read by screen readers, and shown when `showLabel` is set. */
    label?: string;
    showLabel?: boolean;
    disabled?: boolean;
    size?: 'sm' | 'md';
};

/**
 * − 1 + : big enough to tap on a phone, keyboard friendly, never below 1
 * or above `max` (stock, capped at the per-variant limit).
 */
export function QuantityStepper({
    value,
    max,
    onChange,
    label = 'Jumlah',
    showLabel = false,
    disabled = false,
    size = 'md',
}: Props) {
    const id = useId();
    const cell = size === 'sm' ? 'w-8' : 'w-10';

    return (
        <div className="flex items-center gap-3">
            <span
                id={id}
                className={cn(
                    'text-sm text-muted-foreground',
                    !showLabel && 'sr-only',
                )}
            >
                {label}
            </span>
            <div
                role="group"
                aria-labelledby={id}
                className={cn(
                    'inline-flex items-center rounded-md border border-border',
                    size === 'sm' ? 'h-8' : 'h-10',
                    disabled && 'opacity-60',
                )}
            >
                <button
                    type="button"
                    onClick={() => onChange(value - 1)}
                    disabled={disabled || value <= 1}
                    aria-label="Kurangi"
                    className={cn(
                        'grid h-full place-items-center text-muted-foreground hover:text-foreground disabled:opacity-40',
                        cell,
                    )}
                >
                    <Minus className="size-4" />
                </button>
                <span
                    className={cn(
                        'text-center text-sm font-medium tabular-nums',
                        cell,
                    )}
                    aria-live="polite"
                >
                    {value}
                </span>
                <button
                    type="button"
                    onClick={() => onChange(value + 1)}
                    disabled={disabled || value >= max}
                    aria-label="Tambah"
                    className={cn(
                        'grid h-full place-items-center text-muted-foreground hover:text-foreground disabled:opacity-40',
                        cell,
                    )}
                >
                    <Plus className="size-4" />
                </button>
            </div>
        </div>
    );
}
