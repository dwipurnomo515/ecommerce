const dateFormat = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

/** "2026-11-16T08:00:00+00:00" → "16 Nov 2026" */
export function formatDate(iso: string | null): string {
    return iso ? dateFormat.format(new Date(iso)) : '—';
}
