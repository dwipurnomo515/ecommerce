import { router, usePage } from '@inertiajs/react';
import { useId } from 'react';

export type Sort = 'terbaru' | 'termurah' | 'termahal';

const labels: Record<Sort, string> = {
    terbaru: 'Terbaru',
    termurah: 'Harga terendah',
    termahal: 'Harga tertinggi',
};

/**
 * Changes ?sort= in the current URL (keeping ?q=) and starts again at
 * page 1: page 3 of "terbaru" means nothing once sorted by price.
 */
export function SortSelect({ value }: { value: Sort }) {
    const { url } = usePage();
    const id = useId();

    const change = (sort: Sort) => {
        const next = new URL(url, window.location.origin);
        next.searchParams.delete('page');

        if (sort === 'terbaru') {
            next.searchParams.delete('sort'); // the default: keep URLs short
        } else {
            next.searchParams.set('sort', sort);
        }

        router.get(next.pathname + next.search, {}, { preserveScroll: true });
    };

    return (
        <div className="flex items-center gap-2 text-sm">
            <label htmlFor={id} className="text-muted-foreground">
                Urutkan
            </label>
            <select
                id={id}
                value={value}
                onChange={(e) => change(e.target.value as Sort)}
                className="h-9 rounded-md border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                {(Object.keys(labels) as Sort[]).map((sort) => (
                    <option key={sort} value={sort}>
                        {labels[sort]}
                    </option>
                ))}
            </select>
        </div>
    );
}
