import { useForm } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Seller/ProductController';
import InputError from '@/components/input-error';
import { ProductOptionsEditor } from '@/components/seller/product-options-editor';
import { VariantEditor } from '@/components/seller/variant-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { flattenTree } from '@/lib/category-tree';
import { cn } from '@/lib/utils';
import {
    cleanOptions,
    emptyVariant,
    syncVariants,
    variantKey,
    variantLabel,
} from '@/lib/variants';
import type { CleanOption, OptionDraft, VariantDraft } from '@/lib/variants';

export type CategoryChoice = {
    id: number;
    parent_id: number | null;
    name: string;
    is_active: boolean;
};

export type FormLimits = {
    options: number;
    valuesPerOption: number;
    variants: number;
};

export type ProductStatus = 'draft' | 'active' | 'archived';

/** A product as the edit page receives it (variants: active ones only). */
export type EditableProduct = {
    id: number;
    name: string;
    category_id: number;
    description: string | null;
    status: ProductStatus;
    options: CleanOption[];
    variants: {
        options: Record<string, string>;
        sku: string | null;
        price: number;
        compare_at_price: number | null;
        stock: number;
        weight_grams: number;
    }[];
};

type Props = {
    categories: CategoryChoice[];
    limits: FormLimits;
    /** Omitted on the create page. */
    product?: EditableProduct;
};

type FormData = {
    name: string;
    category_id: string;
    description: string;
    status: ProductStatus;
    variants: VariantDraft[];
};

const statusChoices: { value: ProductStatus; label: string; hint: string }[] = [
    {
        value: 'active',
        label: 'Aktif',
        hint: 'Tampil di toko dan bisa dibeli.',
    },
    {
        value: 'draft',
        label: 'Draf',
        hint: 'Disembunyikan, masih disiapkan.',
    },
    {
        value: 'archived',
        label: 'Diarsipkan',
        hint: 'Tidak dijual lagi. Riwayat pesanan tetap ada.',
    },
];

const fieldClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm';

/** Server data → what the inputs hold (null becomes '' so fields can be cleared). */
function toDrafts(product: EditableProduct): {
    options: OptionDraft[];
    variants: VariantDraft[];
} {
    const variants = product.variants.map((v) => ({
        options: v.options,
        sku: v.sku ?? '',
        price: v.price,
        compare_at_price: v.compare_at_price ?? ('' as const),
        stock: v.stock,
        weight_grams: v.weight_grams,
    }));

    return {
        options: product.options.map((o, i) => ({
            id: i + 1,
            name: o.name,
            valuesText: o.values.join(', '),
        })),
        // The database returns option keys sorted (jsonb). Re-syncing with
        // the option list puts rows and labels back in the seller's order.
        variants: syncVariants(product.options, variants),
    };
}

/**
 * The product form, shared by the create and edit pages. useForm (not
 * <Form>) because it is dynamic: the variant rows are generated from the
 * options, and the payload is built in code.
 */
export function ProductForm({ categories, limits, product }: Props) {
    const editing = product !== undefined;
    const tree = useMemo(() => flattenTree(categories), [categories]);

    // useState initialisers run once, so we compute the drafts once too.
    const [initial] = useState(() => (product ? toDrafts(product) : null));

    // Options live outside the form data: the server gets the cleaned
    // version (see transform below), not the raw "Hitam, Putih" text.
    const [options, setOptions] = useState<OptionDraft[]>(
        initial?.options ?? [],
    );
    const nextOptionId = useRef(options.length + 1);

    const form = useForm<FormData>({
        name: product?.name ?? '',
        category_id: product ? String(product.category_id) : '',
        description: product?.description ?? '',
        status: product?.status ?? 'active',
        variants: initial?.variants ?? [emptyVariant()],
    });

    // Server keys look like "variants.2.price"; index them as plain strings.
    const errors = form.errors as Record<string, string | undefined>;
    const comboCount = form.data.variants.length;
    const tooMany = comboCount > limits.variants;

    // Variants on sale now (props, refreshed after every save) that the
    // current form no longer contains: they will be taken off sale.
    const removed = useMemo(() => {
        if (!product) {
            return [];
        }

        const kept = new Set(
            form.data.variants.map((v) => variantKey(v.options)),
        );

        return (
            product.variants
                .filter((v) => !kept.has(variantKey(v.options)))
                // Labels in the seller's option order ("Hitam / L"), not
                // the database's sorted key order ("L / Hitam").
                .map((v) =>
                    Object.fromEntries(
                        product.options.map((o) => [o.name, v.options[o.name]]),
                    ),
                )
        );
    }, [product, form.data.variants]);

    const changeOptions = (next: OptionDraft[]) => {
        setOptions(next);
        form.setData(
            'variants',
            syncVariants(cleanOptions(next), form.data.variants),
        );
    };

    const submit = (status: ProductStatus) => {
        form.transform((data) => ({
            ...data,
            status,
            options: cleanOptions(options),
            variants: data.variants.map((v) => ({
                ...v,
                sku: v.sku.trim() === '' ? null : v.sku.trim(),
                compare_at_price:
                    v.compare_at_price === '' ? null : v.compare_at_price,
            })),
        }));
        form.submit(
            product
                ? ProductController.update(product.id)
                : ProductController.store(),
            { preserveScroll: true },
        );
    };

    return (
        <form
            className="grid gap-10"
            onSubmit={(e) => {
                e.preventDefault();
                submit(editing ? form.data.status : 'active');
            }}
        >
            <Section title="Informasi produk">
                <div className="grid gap-2">
                    <Label htmlFor="product-name">Nama produk</Label>
                    <Input
                        id="product-name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        maxLength={200}
                        required
                        aria-invalid={!!errors.name}
                        placeholder="Contoh: Kaos Polos Katun Combed 30s"
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="product-category">Kategori</Label>
                    <select
                        id="product-category"
                        value={form.data.category_id}
                        onChange={(e) =>
                            form.setData('category_id', e.target.value)
                        }
                        required
                        aria-invalid={!!errors.category_id}
                        className={cn(fieldClass, 'h-9 py-1')}
                    >
                        <option value="" disabled>
                            Pilih kategori
                        </option>
                        {tree.map((c) => (
                            <option
                                key={c.id}
                                value={c.id}
                                // The product's current category stays
                                // selectable even if it was deactivated
                                // (the server allows keeping it).
                                disabled={
                                    !c.available &&
                                    c.id !== product?.category_id
                                }
                            >
                                {'    '.repeat(c.level - 1)}
                                {c.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.category_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="product-description">
                        Deskripsi{' '}
                        <span className="font-normal text-muted-foreground">
                            (opsional)
                        </span>
                    </Label>
                    <textarea
                        id="product-description"
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        rows={6}
                        maxLength={10000}
                        aria-invalid={!!errors.description}
                        placeholder="Bahan, ukuran, cara perawatan, isi paket..."
                        className={fieldClass}
                    />
                    <InputError message={errors.description} />
                </div>
            </Section>

            {editing && (
                <Section title="Status">
                    <fieldset className="grid gap-2 sm:grid-cols-3">
                        <legend className="sr-only">Status produk</legend>
                        {statusChoices.map((choice) => (
                            <label
                                key={choice.value}
                                className="grid cursor-pointer gap-0.5 rounded-lg border border-border p-3 has-checked:border-primary has-checked:bg-primary-soft has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                            >
                                <span className="flex items-center gap-2 text-sm font-medium">
                                    <input
                                        type="radio"
                                        name="status"
                                        value={choice.value}
                                        checked={
                                            form.data.status === choice.value
                                        }
                                        onChange={() =>
                                            form.setData('status', choice.value)
                                        }
                                        className="accent-primary"
                                    />
                                    {choice.label}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {choice.hint}
                                </span>
                            </label>
                        ))}
                    </fieldset>
                    <InputError message={errors.status} />
                </Section>
            )}

            <Section
                title="Pilihan varian"
                description={`Opsional. Maksimal ${limits.options} pilihan, misalnya Warna dan Ukuran. Setiap kombinasi punya harga dan stok sendiri.`}
            >
                <ProductOptionsEditor
                    options={options}
                    max={limits.options}
                    errors={errors}
                    onChange={changeOptions}
                    onAdd={() =>
                        changeOptions([
                            ...options,
                            {
                                id: nextOptionId.current++,
                                name: '',
                                valuesText: '',
                            },
                        ])
                    }
                />
            </Section>

            <Section
                title={
                    comboCount > 1
                        ? `Harga dan stok (${comboCount} varian)`
                        : 'Harga dan stok'
                }
            >
                {removed.length > 0 && (
                    <div
                        role="status"
                        className="flex gap-3 rounded-lg border border-warning/30 p-4"
                    >
                        <TriangleAlert
                            className="mt-0.5 size-5 shrink-0 text-warning"
                            aria-hidden
                        />
                        <div className="grid gap-1 text-sm">
                            <p className="font-medium">
                                {removed.length} varian akan berhenti dijual
                                saat disimpan
                            </p>
                            <p className="text-muted-foreground">
                                {removed
                                    .map(
                                        (options) =>
                                            variantLabel(options) ||
                                            'Varian tanpa pilihan',
                                    )
                                    .join(', ')}
                                . Riwayat pesanannya tetap aman.
                            </p>
                        </div>
                    </div>
                )}

                {tooMany ? (
                    <p className="text-sm text-destructive">
                        {comboCount} kombinasi terlalu banyak. Maksimal{' '}
                        {limits.variants} varian per produk; kurangi isi
                        pilihan.
                    </p>
                ) : (
                    <VariantEditor
                        variants={form.data.variants}
                        errors={errors}
                        onChange={(variants) =>
                            form.setData('variants', variants)
                        }
                    />
                )}
            </Section>

            {/* Sticky action bar: always reachable on long forms and on phones */}
            <div className="fixed inset-x-0 bottom-0 z-10 border-t border-border bg-background/95 px-4 py-3 backdrop-blur md:static md:border-0 md:bg-transparent md:p-0 md:backdrop-blur-none">
                <div className="mx-auto flex max-w-3xl flex-wrap items-center justify-end gap-2">
                    {form.hasErrors && (
                        <p className="mr-auto text-sm text-destructive">
                            Ada isian yang perlu diperbaiki.
                        </p>
                    )}
                    {editing ? (
                        <Button
                            type="submit"
                            disabled={form.processing || tooMany}
                        >
                            Simpan perubahan
                        </Button>
                    ) : (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing || tooMany}
                                onClick={() => submit('draft')}
                            >
                                Simpan draf
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing || tooMany}
                            >
                                Terbitkan
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </form>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-5">
            <div className="grid gap-1 border-b border-border pb-3">
                <h2 className="font-semibold">{title}</h2>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}
