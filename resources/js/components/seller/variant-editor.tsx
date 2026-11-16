import { useState } from 'react';
import { NumberField } from '@/components/seller/number-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { variantLabel } from '@/lib/variants';
import type { VariantDraft } from '@/lib/variants';

type NumberKey = 'price' | 'compare_at_price' | 'stock' | 'weight_grams';

type Props = {
    variants: VariantDraft[];
    errors: Record<string, string | undefined>;
    onChange: (variants: VariantDraft[]) => void;
};

/**
 * One block per variant. A list of small blocks (not a wide table) so it
 * also works on a phone: the 4 numbers sit in 2 columns there, 4 on desktop.
 */
export function VariantEditor({ variants, errors, onChange }: Props) {
    const update = (index: number, patch: Partial<VariantDraft>) =>
        onChange(
            variants.map((v, i) => (i === index ? { ...v, ...patch } : v)),
        );

    const isSimple =
        variants.length === 1 && variantLabel(variants[0].options) === '';

    return (
        <div className="grid gap-4">
            {variants.length > 1 && (
                <ApplyToAll
                    onApply={(patch) =>
                        onChange(variants.map((v) => ({ ...v, ...patch })))
                    }
                />
            )}

            {errors.variants && (
                <p className="text-sm text-destructive">{errors.variants}</p>
            )}

            <ul className="grid gap-3">
                {variants.map((variant, i) => {
                    const err = (field: string) =>
                        errors[`variants.${i}.${field}`];
                    const field = (key: NumberKey) => ({
                        id: `variant-${i}-${key}`,
                        value: variant[key],
                        error: err(key),
                        onChange: (value: number | '') =>
                            update(i, { [key]: value }),
                    });

                    return (
                        <li
                            key={JSON.stringify(variant.options)}
                            className={
                                isSimple
                                    ? 'grid gap-3'
                                    : 'grid gap-3 rounded-lg border border-border p-3'
                            }
                        >
                            {!isSimple && (
                                <div className="flex flex-wrap items-baseline justify-between gap-2">
                                    <p className="font-medium">
                                        {variantLabel(variant.options)}
                                    </p>
                                    {err('options') && (
                                        <p className="text-xs text-destructive">
                                            {err('options')}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <NumberField
                                    {...field('price')}
                                    label="Harga"
                                    prefix="Rp"
                                    placeholder="75000"
                                />
                                <NumberField
                                    {...field('compare_at_price')}
                                    label="Harga coret (opsional)"
                                    prefix="Rp"
                                />
                                <NumberField
                                    {...field('stock')}
                                    label="Stok"
                                    placeholder="0"
                                />
                                <NumberField
                                    {...field('weight_grams')}
                                    label="Berat"
                                    suffix="gram"
                                    min={1}
                                    placeholder="200"
                                />
                            </div>

                            <div className="grid gap-1.5 sm:max-w-xs">
                                <Label
                                    htmlFor={`variant-${i}-sku`}
                                    className="text-xs"
                                >
                                    SKU (opsional)
                                </Label>
                                <Input
                                    id={`variant-${i}-sku`}
                                    value={variant.sku}
                                    maxLength={64}
                                    aria-invalid={!!err('sku')}
                                    onChange={(e) =>
                                        update(i, { sku: e.target.value })
                                    }
                                />
                                {err('sku') && (
                                    <p className="text-xs text-destructive">
                                        {err('sku')}
                                    </p>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

/** Fill price, stock and weight for every variant at once. */
function ApplyToAll({
    onApply,
}: {
    onApply: (patch: Partial<VariantDraft>) => void;
}) {
    const [price, setPrice] = useState<number | ''>('');
    const [stock, setStock] = useState<number | ''>('');
    const [weight, setWeight] = useState<number | ''>('');

    const apply = () => {
        const patch: Partial<VariantDraft> = {};

        // Only the boxes the seller actually filled in
        if (price !== '') patch.price = price;
        if (stock !== '') patch.stock = stock;
        if (weight !== '') patch.weight_grams = weight;

        onApply(patch);
    };

    return (
        <div className="grid gap-3 rounded-lg bg-muted p-3">
            <p className="text-sm font-medium">
                Isi sekaligus untuk semua varian
            </p>
            <div className="grid grid-cols-2 items-end gap-3 md:grid-cols-4">
                <NumberField
                    id="all-price"
                    label="Harga"
                    prefix="Rp"
                    value={price}
                    onChange={setPrice}
                />
                <NumberField
                    id="all-stock"
                    label="Stok"
                    value={stock}
                    onChange={setStock}
                />
                <NumberField
                    id="all-weight"
                    label="Berat"
                    suffix="gram"
                    min={1}
                    value={weight}
                    onChange={setWeight}
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={apply}
                    disabled={price === '' && stock === '' && weight === ''}
                >
                    Terapkan ke semua
                </Button>
            </div>
        </div>
    );
}
