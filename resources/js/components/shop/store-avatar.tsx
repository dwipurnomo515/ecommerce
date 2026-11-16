import { cn } from '@/lib/utils';

/**
 * The store's initial in a soft circle. Stands in for a logo until stores
 * can upload one (logo_path exists in the table, the upload comes later).
 */
export function StoreAvatar({
    name,
    size = 'md',
}: {
    name: string;
    size?: 'md' | 'lg';
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'grid shrink-0 place-items-center rounded-full bg-primary-soft font-semibold text-primary',
                size === 'lg' ? 'size-16 text-2xl' : 'size-11 text-base',
            )}
        >
            {name.trim().charAt(0).toUpperCase()}
        </span>
    );
}

const joinedFormat = new Intl.DateTimeFormat('id-ID', {
    month: 'long',
    year: 'numeric',
});

/** "Bergabung November 2026", or null for a store with no approval date. */
export function joinedText(iso: string | null): string | null {
    return iso ? `Bergabung ${joinedFormat.format(new Date(iso))}` : null;
}
