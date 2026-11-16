/**
 * Buyer-side variant choice ("Warna: Hitam", then "Ukuran: L"). Pure
 * functions, no React, so the rules can be checked on their own.
 */

export type BuyerVariant = {
    id: number;
    options: Record<string, string>;
    price: number;
    compare_at_price: number | null;
    stock: number;
};

export type Selection = Record<string, string>;

/** Does the variant have every option value in `selection`? */
export function matches(variant: BuyerVariant, selection: Selection): boolean {
    return Object.entries(selection).every(
        ([name, value]) => variant.options[name] === value,
    );
}

/** The variant for a COMPLETE selection, or null while something is unchosen. */
export function selectedVariant(
    variants: BuyerVariant[],
    optionNames: string[],
    selection: Selection,
): BuyerVariant | null {
    if (!optionNames.every((name) => selection[name] !== undefined)) {
        return null;
    }

    return variants.find((v) => matches(v, selection)) ?? null;
}

/**
 * Start on the first variant in stock (or the first one if all are sold
 * out), so the page shows a real price and stock right away.
 */
export function initialSelection(variants: BuyerVariant[]): Selection {
    const first = variants.find((v) => v.stock > 0) ?? variants[0];

    return first ? { ...first.options } : {};
}

/**
 * How a value button looks, given what is chosen for the OTHER options:
 * - 'available': some matching variant has stock
 * - 'sold-out':  the combination exists but has no stock
 * - 'missing':   the seller doesn't sell this combination at all
 */
export function valueState(
    variants: BuyerVariant[],
    selection: Selection,
    optionName: string,
    value: string,
): 'available' | 'sold-out' | 'missing' {
    const candidates = variants.filter((v) =>
        matches(v, { ...selection, [optionName]: value }),
    );

    if (candidates.length === 0) {
        return 'missing';
    }

    return candidates.some((v) => v.stock > 0) ? 'available' : 'sold-out';
}

/**
 * Picking a value. If the result is a combination that doesn't exist
 * (Putih chosen while "XL" only comes in Hitam), switch the other options
 * to the closest real variant with that value, preferring one in stock,
 * instead of leaving the buyer on a dead end.
 */
export function choose(
    variants: BuyerVariant[],
    selection: Selection,
    optionName: string,
    value: string,
): Selection {
    const next = { ...selection, [optionName]: value };

    if (variants.some((v) => matches(v, next))) {
        return next;
    }

    const withValue = variants.filter((v) => v.options[optionName] === value);
    const fallback = withValue.find((v) => v.stock > 0) ?? withValue[0];

    return fallback ? { ...fallback.options } : next;
}

/** Lowest and highest price, for "Rp 75.000 – Rp 80.000". */
export function priceRange(variants: BuyerVariant[]): {
    min: number;
    max: number;
} {
    const prices = variants.map((v) => v.price);

    return { min: Math.min(...prices), max: Math.max(...prices) };
}
