import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { CategoryFormDialog } from '@/components/admin/category-form-dialog';
import type { CategoryDialogState } from '@/components/admin/category-form-dialog';
import { DeleteCategoryDialog } from '@/components/admin/delete-category-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { allowedParents, flattenTree } from '@/lib/category-tree';
import type { CategoryNode } from '@/lib/category-tree';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/categories';
import type { AdminCategory } from '@/types/catalog';

type Props = {
    categories: AdminCategory[];
    maxDepth: number;
};

export default function CategoriesIndex({ categories, maxDepth }: Props) {
    const nodes = useMemo(() => flattenTree(categories), [categories]);
    const [dialog, setDialog] = useState<CategoryDialogState | null>(null);
    const [deleting, setDeleting] = useState<CategoryNode | null>(null);

    const parentOptions = useMemo(
        () =>
            allowedParents(
                nodes,
                dialog?.mode === 'edit' ? dialog.category : null,
                maxDepth,
            ),
        [nodes, dialog, maxDepth],
    );

    return (
        <>
            <Head title="Kategori" />

            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Kategori"
                        description={`Pohon kategori marketplace, maksimal ${maxDepth} level. Penjual memilih kategori dari daftar ini.`}
                    />
                    <Button
                        onClick={() =>
                            setDialog({ mode: 'create', parentId: null })
                        }
                    >
                        <Plus />
                        Tambah kategori
                    </Button>
                </div>

                {nodes.length === 0 ? (
                    <EmptyState
                        onCreate={() =>
                            setDialog({ mode: 'create', parentId: null })
                        }
                    />
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border bg-card">
                        {nodes.map((node) => (
                            <CategoryRow
                                key={node.id}
                                node={node}
                                canHaveChildren={node.level < maxDepth}
                                onAddChild={() =>
                                    setDialog({
                                        mode: 'create',
                                        parentId: node.id,
                                    })
                                }
                                onEdit={() =>
                                    setDialog({ mode: 'edit', category: node })
                                }
                                onDelete={() => setDeleting(node)}
                            />
                        ))}
                    </ul>
                )}
            </div>

            <CategoryFormDialog
                state={dialog}
                parentOptions={parentOptions}
                onClose={() => setDialog(null)}
            />
            <DeleteCategoryDialog
                category={deleting}
                onClose={() => setDeleting(null)}
            />
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Kategori', href: index() }],
};

function CategoryRow({
    node,
    canHaveChildren,
    onAddChild,
    onEdit,
    onDelete,
}: {
    node: CategoryNode;
    canHaveChildren: boolean;
    onAddChild: () => void;
    onEdit: () => void;
    onDelete: () => void;
}) {
    return (
        <li
            className={cn(
                'flex flex-wrap items-center gap-x-4 gap-y-2 py-3 pr-2',
                // Indent by level so the tree shape is visible at a glance
                node.level === 1 && 'pl-4',
                node.level === 2 && 'pl-10',
                node.level === 3 && 'pl-16',
            )}
        >
            <div className="grid min-w-0 flex-1 gap-0.5">
                <div className="flex min-w-0 flex-wrap items-center gap-x-2">
                    <span
                        className={cn(
                            'truncate',
                            node.level === 1 ? 'font-semibold' : 'font-medium',
                            !node.is_active && 'text-muted-foreground',
                        )}
                    >
                        {node.name}
                    </span>
                    {!node.is_active && (
                        <span className="rounded border border-border px-1.5 text-xs text-muted-foreground">
                            Nonaktif
                        </span>
                    )}
                </div>
                <div className="flex flex-wrap gap-x-3 text-xs text-muted-foreground tabular-nums">
                    <span className="truncate">/{node.slug}</span>
                    <span>{node.products_count} produk</span>
                    {node.childCount > 0 && (
                        <span>{node.childCount} subkategori</span>
                    )}
                </div>
            </div>

            <div className="flex items-center gap-1">
                {canHaveChildren && (
                    <Button variant="ghost" size="sm" onClick={onAddChild}>
                        <Plus />
                        <span>Sub</span>
                        <span className="sr-only">kategori di {node.name}</span>
                    </Button>
                )}
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={onEdit}
                    aria-label={`Edit ${node.name}`}
                >
                    <Pencil />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={onDelete}
                    aria-label={`Hapus ${node.name}`}
                    className="text-muted-foreground hover:text-destructive"
                >
                    <Trash2 />
                </Button>
            </div>
        </li>
    );
}

function EmptyState({ onCreate }: { onCreate: () => void }) {
    return (
        <div className="grid justify-items-center gap-3 rounded-lg border border-dashed border-border px-6 py-14 text-center">
            <p className="font-medium">Belum ada kategori</p>
            <p className="max-w-sm text-sm text-muted-foreground">
                Mulai dengan kategori utama seperti Fashion, Elektronik, atau
                Rumah Tangga, lalu tambahkan subkategori di dalamnya.
            </p>
            <Button variant="outline" onClick={onCreate}>
                <Plus />
                Tambah kategori pertama
            </Button>
        </div>
    );
}
