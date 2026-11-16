import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    label: string;
    value: number | '';
    onChange: (value: number | '') => void;
    error?: string;
    prefix?: string;
    suffix?: string;
    placeholder?: string;
    min?: number;
    /** Visually hide the label (it is still read by screen readers). */
    hideLabel?: boolean;
};

/**
 * Whole-number input with an optional "Rp" prefix or "gram" suffix.
 * Empty stays '' (not 0) so the seller can clear the field.
 */
export function NumberField({
    id,
    label,
    value,
    onChange,
    error,
    prefix,
    suffix,
    placeholder,
    min = 0,
    hideLabel = false,
}: Props) {
    return (
        <div className="grid min-w-0 gap-1.5">
            <Label
                htmlFor={id}
                className={cn('text-xs', hideLabel && 'sr-only')}
            >
                {label}
            </Label>
            <div
                className={cn(
                    'flex h-9 items-center rounded-md border border-input bg-transparent shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                    error && 'border-destructive',
                )}
            >
                {prefix && (
                    <span className="pl-3 text-sm text-muted-foreground select-none">
                        {prefix}
                    </span>
                )}
                <input
                    id={id}
                    type="number"
                    inputMode="numeric"
                    step={1}
                    min={min}
                    value={value}
                    placeholder={placeholder}
                    aria-invalid={!!error}
                    onChange={(e) =>
                        onChange(
                            e.target.value === ''
                                ? ''
                                : Math.trunc(Number(e.target.value)),
                        )
                    }
                    className="h-full w-full min-w-0 [appearance:textfield] bg-transparent px-3 text-base tabular-nums outline-none placeholder:text-muted-foreground md:text-sm [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                />
                {suffix && (
                    <span className="pr-3 text-sm text-muted-foreground select-none">
                        {suffix}
                    </span>
                )}
            </div>
            <InputError message={error} />
        </div>
    );
}
