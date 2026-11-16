import { Plus, X } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { OptionDraft } from '@/lib/variants';

type Props = {
    options: OptionDraft[];
    max: number;
    errors: Record<string, string | undefined>;
    onChange: (options: OptionDraft[]) => void;
    onAdd: () => void;
};

/**
 * "Warna: Hitam, Putih" rows. Values are typed comma-separated: quick on
 * desktop and on a phone keyboard, with no custom tag widget to learn.
 */
export function ProductOptionsEditor({
    options,
    max,
    errors,
    onChange,
    onAdd,
}: Props) {
    const update = (id: number, patch: Partial<OptionDraft>) =>
        onChange(options.map((o) => (o.id === id ? { ...o, ...patch } : o)));

    return (
        <div className="grid gap-4">
            {options.map((option, i) => {
                // Any server error about this option (name, a value, ...)
                const error = Object.entries(errors).find(([key]) =>
                    key.startsWith(`options.${i}.`),
                )?.[1];

                return (
                    <div
                        key={option.id}
                        className="grid gap-3 sm:grid-cols-[10rem_1fr_auto] sm:items-start"
                    >
                        <div className="grid gap-1.5">
                            <Label
                                htmlFor={`option-${option.id}-name`}
                                className="text-xs"
                            >
                                Nama pilihan
                            </Label>
                            <Input
                                id={`option-${option.id}-name`}
                                value={option.name}
                                maxLength={30}
                                placeholder={i === 0 ? 'Warna' : 'Ukuran'}
                                onChange={(e) =>
                                    update(option.id, { name: e.target.value })
                                }
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label
                                htmlFor={`option-${option.id}-values`}
                                className="text-xs"
                            >
                                Isi pilihan, pisahkan dengan koma
                            </Label>
                            <Input
                                id={`option-${option.id}-values`}
                                value={option.valuesText}
                                placeholder={
                                    i === 0
                                        ? 'Hitam, Putih, Navy'
                                        : 'S, M, L, XL'
                                }
                                onChange={(e) =>
                                    update(option.id, {
                                        valuesText: e.target.value,
                                    })
                                }
                            />
                            <InputError message={error} />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="sm:mt-6"
                            aria-label={`Hapus pilihan ${option.name || i + 1}`}
                            onClick={() =>
                                onChange(
                                    options.filter((o) => o.id !== option.id),
                                )
                            }
                        >
                            <X />
                        </Button>
                    </div>
                );
            })}

            {options.length < max && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="justify-self-start"
                    onClick={onAdd}
                >
                    <Plus />
                    {options.length === 0
                        ? 'Tambah pilihan (warna, ukuran, dll.)'
                        : 'Tambah pilihan lain'}
                </Button>
            )}
        </div>
    );
}
