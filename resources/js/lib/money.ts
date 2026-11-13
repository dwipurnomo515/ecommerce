/**
 * Money helpers. Prices travel from the backend as whole Rupiah integers
 * (see the product_variants.price column), so no float maths happens here.
 */

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

/** 249000 → "Rp 249.000" (with a non-breaking space, so it never wraps). */
export function formatRupiah(amount: number): string {
    return rupiah.format(amount);
}

/**
 * Whole-number discount percentage, rounded DOWN so we never advertise a
 * bigger discount than the real one (Rp 299.000 → Rp 249.000 is 16.7% → 16%).
 * Returns null when there is no real discount.
 */
export function discountPercent(
    price: number,
    compareAtPrice: number | null | undefined,
): number | null {
    if (!compareAtPrice || compareAtPrice <= price) {
        return null;
    }

    return Math.floor(((compareAtPrice - price) / compareAtPrice) * 100);
}
