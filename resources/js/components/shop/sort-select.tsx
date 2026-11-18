import { router, usePage } from '@inertiajs/react';
import { useId } from 'react';

export type Sort = 'relevan' | 'terbaru' | 'termurah' | 'termahal';

const labels: Record<Sort, string> = {
    relevan: 'Paling relevan',
    terbaru: 'Terbaru',
    termurah: 'Harga terendah',
    termahal: 'Harga tertinggi',
};

/**
 * Changes ?sort= in the current URL (keeping ?q=) and starts again at
 * page 1: page 3 of "terbaru" means nothing once sorted by price.
 *
 * The page's default order is left out of the URL (short links). On the
 * search page the default is "relevan", so choosing "terbaru" there must
 * be written out explicitly.
 */
export function SortSelect({
    value,
    defaultSort = 'terbaru',
    withRelevance = false,
}: {
    value: Sort;
    defaultSort?: Sort;
    withRelevance?: boolean;
}) {
    const { url } = usePage();
    const id = useId();
    const options = (Object.keys(labels) as Sort[]).filter(
        (s) => withRelevance || s !== 'relevan',
    );

    const change = (sort: Sort) => {
        const next = new URL(url, window.location.origin);
        next.searchParams.delete('page');

        if (sort === defaultSort) {
            next.searchParams.delete('sort');
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
                {options.map((sort) => (
                    <option key={sort} value={sort}>
                        {labels[sort]}
                    </option>
                ))}
            </select>
        </div>
    );
}
