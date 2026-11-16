import { Link } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/pagination';

/** "Halaman 2 dari 5" + previous/next. Renders nothing for a single page. */
export function SimplePagination({
    page,
}: {
    page: Omit<Paginated<unknown>, 'data'>;
}) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Halaman"
            className="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span className="tabular-nums">
                Halaman {page.current_page} dari {page.last_page}
            </span>
            <div className="flex gap-2">
                <PageLink url={page.prev_page_url}>Sebelumnya</PageLink>
                <PageLink url={page.next_page_url}>Berikutnya</PageLink>
            </div>
        </nav>
    );
}

function PageLink({ url, children }: { url: string | null; children: string }) {
    const className = buttonVariants({ variant: 'outline', size: 'sm' });

    return url ? (
        <Link href={url} preserveScroll className={className}>
            {children}
        </Link>
    ) : (
        <span
            aria-disabled
            className={cn(className, 'pointer-events-none opacity-50')}
        >
            {children}
        </span>
    );
}
