import { Link } from '@inertiajs/react';
import { show } from '@/routes/shop/categories';

export type CategoryLink = { name: string; slug: string };

/**
 * Categories as a row of text pills. On phones the row scrolls sideways
 * instead of wrapping into a tall block that pushes products down.
 */
export function CategoryLinks({
    categories,
    label,
}: {
    categories: CategoryLink[];
    label: string;
}) {
    if (categories.length === 0) {
        return null;
    }

    return (
        <nav aria-label={label}>
            <ul className="-mx-4 flex [scrollbar-width:none] gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:px-0">
                {categories.map((c) => (
                    <li key={c.slug} className="shrink-0">
                        <Link
                            href={show(c.slug)}
                            className="inline-flex h-9 items-center rounded-full border border-border px-4 text-sm whitespace-nowrap transition-colors hover:border-foreground/40 hover:bg-muted/50"
                        >
                            {c.name}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
