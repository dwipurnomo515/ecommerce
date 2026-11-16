/**
 * Preview-only approximation of Laravel's Str::slug ("Toko Budi!" → "toko-budi").
 * The server always generates the real slug.
 */
export function slugify(text: string): string {
    return text
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
