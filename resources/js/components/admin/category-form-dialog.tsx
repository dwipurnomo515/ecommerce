import { Form } from '@inertiajs/react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CategoryNode } from '@/lib/category-tree';
import { cn } from '@/lib/utils';

/** Same look as <Input>, for the native <select> and <textarea>. */
const fieldClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm';

export type CategoryDialogState =
    | { mode: 'create'; parentId: number | null }
    | { mode: 'edit'; category: CategoryNode };

type Props = {
    state: CategoryDialogState | null;
    parentOptions: CategoryNode[];
    onClose: () => void;
};

export function CategoryFormDialog({ state, parentOptions, onClose }: Props) {
    return (
        <Dialog
            open={state !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-lg">
                {state && (
                    // `key` resets the form when switching between categories
                    <CategoryForm
                        key={
                            state.mode === 'edit'
                                ? state.category.id
                                : `new-${state.parentId}`
                        }
                        state={state}
                        parentOptions={parentOptions}
                        onSuccess={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function CategoryForm({
    state,
    parentOptions,
    onSuccess,
}: {
    state: CategoryDialogState;
    parentOptions: CategoryNode[];
    onSuccess: () => void;
}) {
    const editing = state.mode === 'edit' ? state.category : null;
    const [isActive, setIsActive] = useState(editing?.is_active ?? true);
    const defaultParent =
        state.mode === 'edit' ? state.category.parent_id : state.parentId;

    const formAction = editing
        ? CategoryController.update.form(editing.id)
        : CategoryController.store.form();

    return (
        <Form
            {...formAction}
            options={{ preserveScroll: true }}
            onSuccess={onSuccess}
            className="grid gap-5"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-1.5">
                        <DialogTitle>
                            {editing ? 'Edit kategori' : 'Tambah kategori'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Perubahan langsung terlihat oleh penjual dan pembeli.'
                                : 'Kategori baru langsung bisa dipilih penjual.'}
                        </DialogDescription>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="category-name">Nama</Label>
                        <Input
                            id="category-name"
                            name="name"
                            defaultValue={editing?.name}
                            required
                            maxLength={100}
                            autoFocus
                            aria-invalid={!!errors.name}
                            placeholder="Contoh: Fashion Pria"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="category-slug">Slug (alamat URL)</Label>
                        <Input
                            id="category-slug"
                            name="slug"
                            defaultValue={editing?.slug}
                            maxLength={120}
                            aria-invalid={!!errors.slug}
                            placeholder="Kosongkan untuk dibuat otomatis dari nama"
                        />
                        <InputError message={errors.slug} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="category-parent">Induk kategori</Label>
                        <select
                            id="category-parent"
                            name="parent_id"
                            defaultValue={defaultParent ?? ''}
                            aria-invalid={!!errors.parent_id}
                            className={cn(fieldClass, 'h-9 py-1')}
                        >
                            <option value="">Tidak ada (kategori utama)</option>
                            {parentOptions.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {/* non-breaking spaces show the tree depth */}
                                    {'    '.repeat(option.level - 1)}
                                    {option.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.parent_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="category-description">
                            Deskripsi{' '}
                            <span className="font-normal text-muted-foreground">
                                (opsional)
                            </span>
                        </Label>
                        <textarea
                            id="category-description"
                            name="description"
                            defaultValue={editing?.description ?? ''}
                            rows={3}
                            maxLength={1000}
                            aria-invalid={!!errors.description}
                            className={fieldClass}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid grid-cols-[8rem_1fr] items-start gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="category-sort">Urutan</Label>
                            <Input
                                id="category-sort"
                                name="sort_order"
                                type="number"
                                min={0}
                                max={65535}
                                defaultValue={editing?.sort_order ?? 0}
                                aria-invalid={!!errors.sort_order}
                            />
                            <InputError message={errors.sort_order} />
                        </div>

                        <div className="grid gap-2 pt-7">
                            <div className="flex items-center gap-2">
                                {/* An unchecked checkbox sends nothing, so the
                                    real value travels in this hidden input. */}
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value={isActive ? '1' : '0'}
                                />
                                <Checkbox
                                    id="category-active"
                                    checked={isActive}
                                    onCheckedChange={(checked) =>
                                        setIsActive(checked === true)
                                    }
                                />
                                <Label htmlFor="category-active">Aktif</Label>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Kategori nonaktif disembunyikan dari pembeli.
                            </p>
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {editing ? 'Simpan perubahan' : 'Tambah kategori'}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}
