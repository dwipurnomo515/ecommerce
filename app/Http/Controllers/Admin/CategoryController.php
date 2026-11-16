<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * The whole tree as a flat list (it is small: a few hundred rows at most).
     * The page builds the tree from parent_id and reuses the list for the
     * "parent" dropdown.
     */
    public function index(): Response
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'description', 'is_active', 'sort_order']);

        return Inertia::render('admin/categories/index', [
            'categories' => $categories,
            'maxDepth' => Category::MAX_DEPTH,
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori \"{$category->name}\" ditambahkan."]);

        return to_route('admin.categories.index');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori \"{$category->name}\" diperbarui."]);

        return to_route('admin.categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Explain the problem instead of letting the DB foreign key (RESTRICT)
        // fail with a 500. Soft-deleted products still reference the category,
        // so they count too.
        $reason = match (true) {
            $category->children()->exists() => 'masih punya subkategori. Hapus atau pindahkan subkategorinya dulu.',
            $category->products()->withTrashed()->exists() => 'masih dipakai oleh produk. Nonaktifkan saja kategorinya.',
            default => null,
        };

        if ($reason !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Kategori \"{$category->name}\" tidak bisa dihapus: {$reason}"]);

            return to_route('admin.categories.index');
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori \"{$category->name}\" dihapus."]);

        return to_route('admin.categories.index');
    }
}
