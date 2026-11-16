/**
 * Pure helpers for the product form's variant table. No React here, so the
 * logic can be tested on its own (see the Node check in the docs).
 */

/** One option as the seller types it: "Warna" + "Hitam, Putih". */
export type OptionDraft = {
    id: number;
    name: string;
    valuesText: string;
};

export type CleanOption = { name: string; values: string[] };

/** Number fields stay '' while empty, so the input can be cleared. */
export type VariantDraft = {
    options: Record<string, string>;
    sku: string;
    price: number | '';
    compare_at_price: number | '';
    stock: number | '';
    weight_grams: number | '';
};

export const emptyVariant = (
    options: Record<string, string> = {},
): VariantDraft => ({
    options,
    sku: '',
    price: '',
    compare_at_price: '',
    stock: '',
    weight_grams: '',
});

/**
 * Trims names and values, drops empty values and duplicate values
 * (case-insensitive), and drops options without a name or without values.
 */
export function cleanOptions(drafts: OptionDraft[]): CleanOption[] {
    return drafts
        .map((draft) => {
            const seen = new Set<string>();
            const values = draft.valuesText
                .split(',')
                .map((v) => v.trim())
                .filter((v) => {
                    const key = v.toLowerCase();

                    if (v === '' || seen.has(key)) {
                        return false;
                    }

                    seen.add(key);

                    return true;
                });

            return { name: draft.name.trim(), values };
        })
        .filter((option) => option.name !== '' && option.values.length > 0);
}

/**
 * Every combination of the option values, in a stable order:
 * Warna [Hitam, Putih] x Ukuran [M, L] →
 * Hitam/M, Hitam/L, Putih/M, Putih/L.
 * No options → one combination with no options (a simple product).
 */
export function combinations(options: CleanOption[]): Record<string, string>[] {
    return options.reduce<Record<string, string>[]>(
        (combos, option) =>
            combos.flatMap((combo) =>
                option.values.map((value) => ({
                    ...combo,
                    [option.name]: value,
                })),
            ),
        [{}],
    );
}

/** Same key for {Warna: A, Ukuran: B} and {Ukuran: B, Warna: A}. */
export function variantKey(options: Record<string, string>): string {
    return JSON.stringify(
        Object.entries(options).sort(([a], [b]) => a.localeCompare(b)),
    );
}

/**
 * Rebuilds the variant rows after the options change, KEEPING whatever the
 * seller already typed for combinations that still exist. New combinations
 * start empty.
 */
export function syncVariants(
    options: CleanOption[],
    previous: VariantDraft[],
): VariantDraft[] {
    const byKey = new Map(previous.map((v) => [variantKey(v.options), v]));

    return combinations(options).map((combo) => {
        const existing = byKey.get(variantKey(combo));

        // Re-use the typed values, but always take the options from the new
        // combo so the display order follows the current option order.
        return existing ? { ...existing, options: combo } : emptyVariant(combo);
    });
}

/** "Hitam / XL", or '' for a simple product. */
export function variantLabel(options: Record<string, string>): string {
    return Object.values(options).join(' / ');
}
