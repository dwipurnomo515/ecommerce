/**
 * Couriers state delivery time in English ("2-3 day", "1 DAY", "6 hours").
 * Show it in Indonesian; unknown formats pass through unchanged.
 */
export function formatEtd(etd: string): string {
    const text = etd.trim();

    if (text === '') {
        return '';
    }

    return text
        .replace(/\s*days?\b/i, ' hari')
        .replace(/\s*hours?\b/i, ' jam')
        .replace(/-/g, '–');
}
