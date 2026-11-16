import { router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useId, useState } from 'react';
import { search } from '@/routes/shop';

/**
 * A plain GET search: the query ends up in the URL (/cari?q=kaos), so
 * results can be shared and the back button works.
 */
export function SearchForm({ className }: { className?: string }) {
    const { url } = usePage();
    // Keep the current query in the box while browsing results.
    const current = new URL(url, 'http://x').searchParams.get('q') ?? '';
    const [q, setQ] = useState(current);
    // The layout renders two of these (phone + desktop): ids must differ.
    const id = useId();

    return (
        <form
            role="search"
            className={className}
            onSubmit={(e) => {
                e.preventDefault();
                const query = q.trim();
                router.get(search.url({ query: query ? { q: query } : {} }));
            }}
        >
            <div className="relative">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden
                />
                <label htmlFor={id} className="sr-only">
                    Cari produk
                </label>
                <input
                    id={id}
                    type="search"
                    name="q"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Cari produk"
                    maxLength={200}
                    enterKeyHint="search"
                    className="h-10 w-full rounded-full border border-input bg-muted/40 pr-4 pl-9 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:bg-background focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                />
            </div>
        </form>
    );
}
