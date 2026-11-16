import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { slugify } from '@/lib/slug';

type Props = {
    errors: Partial<Record<'name' | 'slug' | 'description', string>>;
    defaults?: { name: string; slug: string; description: string | null };
    /** False once the store is approved: its address is then frozen. */
    slugEditable: boolean;
};

/** Shared by "Buka toko" and the store profile page. */
export function StoreProfileFields({ errors, defaults, slugEditable }: Props) {
    const [name, setName] = useState(defaults?.name ?? '');
    const [slug, setSlug] = useState(defaults?.slug ?? '');
    const previewSlug = slugify(slug) || slugify(name) || 'nama-toko';

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="store-name">Nama toko</Label>
                <Input
                    id="store-name"
                    name="name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                    minLength={3}
                    maxLength={100}
                    autoComplete="organization"
                    aria-invalid={!!errors.name}
                    placeholder="Contoh: Toko Budi Elektronik"
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="store-slug">Alamat toko</Label>
                {slugEditable ? (
                    <>
                        <div className="flex items-center rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                            <span className="pl-3 text-sm text-muted-foreground select-none">
                                /toko/
                            </span>
                            <input
                                id="store-slug"
                                name="slug"
                                value={slug}
                                onChange={(e) => setSlug(e.target.value)}
                                maxLength={120}
                                aria-invalid={!!errors.slug}
                                aria-describedby="store-slug-help"
                                placeholder={slugify(name) || 'nama-toko'}
                                className="h-9 min-w-0 flex-1 bg-transparent pr-3 text-base outline-none placeholder:text-muted-foreground md:text-sm"
                            />
                        </div>
                        <p
                            id="store-slug-help"
                            className="text-xs text-muted-foreground"
                        >
                            Pembeli akan membuka toko Anda di{' '}
                            <span className="font-medium text-foreground">
                                /toko/{previewSlug}
                            </span>
                            . Alamat tidak bisa diubah setelah toko disetujui.
                        </p>
                    </>
                ) : (
                    <p id="store-slug" className="text-sm">
                        /toko/{defaults?.slug}
                        <span className="block text-xs text-muted-foreground">
                            Alamat toko tidak bisa diubah setelah disetujui,
                            supaya tautan yang sudah dibagikan pembeli tetap
                            berfungsi.
                        </span>
                    </p>
                )}
                <InputError message={errors.slug} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="store-description">
                    Deskripsi{' '}
                    <span className="font-normal text-muted-foreground">
                        (opsional)
                    </span>
                </Label>
                <textarea
                    id="store-description"
                    name="description"
                    defaultValue={defaults?.description ?? ''}
                    rows={4}
                    maxLength={2000}
                    aria-invalid={!!errors.description}
                    placeholder="Ceritakan apa yang Anda jual dan apa yang membuat toko Anda berbeda."
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm"
                />
                <InputError message={errors.description} />
            </div>
        </>
    );
}
