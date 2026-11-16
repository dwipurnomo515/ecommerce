import { Head, useForm } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Seller/ProductController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ProductOptionsEditor } from '@/components/seller/product-options-editor';
import { VariantEditor } from '@/components/seller/variant-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { flattenTree } from '@/lib/category-tree';
import { cleanOptions, emptyVariant, syncVariants } from '@/lib/variants';
import type { OptionDraft, VariantDraft } from '@/lib/variants';
import { cn } from '@/lib/utils';
import { create, index } from '@/routes/seller/products';

type Props = {
    categories: {
        id: number;
        parent_id: number | null;
        name: string;
        is_active: boolean;
    }[];
    limits: { options: number; valuesPerOption: number; variants: number };
};

type ProductForm = {
    name: string;
    category_id: string;
    description: string;
    variants: VariantDraft[];
};

const fieldClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm';

/**
 * useForm (not <Form>) because this form is dynamic: the variant rows are
 * generated from the options, and the payload is built in code.
 */
export default function CreateProduct({ categories, limits }: Props) {
    const tree = useMemo(() => flattenTree(categories), [categories]);

    // Options live outside the form data: the server gets the cleaned
    // version (see transform below), not the raw "Hitam, Putih" text.
    const [options, setOptions] = useState<OptionDraft[]>([]);
    const nextOptionId = useRef(1);

    const form = useForm<ProductForm>({
        name: '',
        category_id: '',
        description: '',
        variants: [emptyVariant()],
    });

    // Server keys look like "variants.2.price"; index them as plain strings.
    const errors = form.errors as Record<string, string | undefined>;
    const comboCount = form.data.variants.length;
    const tooMany = comboCount > limits.variants;

    const changeOptions = (next: OptionDraft[]) => {
        setOptions(next);
        form.setData(
            'variants',
            syncVariants(cleanOptions(next), form.data.variants),
        );
    };

    const submit = (status: 'draft' | 'active') => {
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
        form.submit(ProductController.store(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Tambah produk" />

            <form
                className="mx-auto grid w-full max-w-3xl gap-10 p-4 pb-28 md:p-6 md:pb-28"
                onSubmit={(e) => {
                    e.preventDefault();
                    submit('active');
                }}
            >
                <Heading
                    title="Tambah produk"
                    description="Produk yang diterbitkan langsung tampil di toko Anda. Simpan sebagai draf kalau belum siap."
                />

                <Section title="Informasi produk">
                    <div className="grid gap-2">
                        <Label htmlFor="product-name">Nama produk</Label>
                        <Input
                            id="product-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
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
                                    disabled={!c.available}
                                >
                                    {'    '.repeat(c.level - 1)}
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
                    </div>
                </div>
            </form>
        </>
    );
}

CreateProduct.layout = {
    breadcrumbs: [
        { title: 'Produk', href: index() },
        { title: 'Tambah produk', href: create() },
    ],
};

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
