import { Form } from '@inertiajs/react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import type { CategoryNode } from '@/lib/category-tree';

export function DeleteCategoryDialog({
    category,
    onClose,
}: {
    category: CategoryNode | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={category !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {category && (
                    <Form
                        {...CategoryController.destroy.form(category.id)}
                        options={{ preserveScroll: true }}
                        // The server may refuse (subcategories/products) and
                        // explain why in a toast; either way the dialog closes.
                        onFinish={onClose}
                        className="grid gap-5"
                    >
                        {({ processing }) => (
                            <>
                                <div className="grid gap-1.5">
                                    <DialogTitle>
                                        Hapus kategori "{category.name}"?
                                    </DialogTitle>
                                    <DialogDescription>
                                        Kategori yang dihapus tidak bisa
                                        dikembalikan. Kategori yang masih punya
                                        subkategori atau produk tidak bisa
                                        dihapus. Nonaktifkan saja kalau hanya
                                        ingin menyembunyikannya.
                                    </DialogDescription>
                                </div>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Batal
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        Hapus
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
