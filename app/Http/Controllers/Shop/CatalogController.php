<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Like;
use App\Support\ProductCards;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public browsing: home, category pages and search. All product lists go
 * through ProductCards (and so through Product::visibleToBuyers()).
 */
class CatalogController extends Controller
{
    private const PER_PAGE = 24;

    public function home(): Response
    {
        return Inertia::render('shop/home', [
            'categories' => $this->rootCategories(),
            'products' => ProductCards::query()
                ->limit(12)
                ->get()
                ->map(fn (Product $product) => ProductCards::toCard($product)),
        ]);
    }

    public function category(Request $request, string $slug): Response
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();

        // A hidden category (or one under a hidden parent) does not exist
        // for buyers, same as hidden products.
        abort_unless($category->isSelectable(), 404);

        $sort = $this->sort($request);

        return Inertia::render('shop/category', [
            'category' => [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
            ],
            'parent' => $category->parent_id === null ? null : Category::query()
                ->whereKey($category->parent_id)
                ->first(['name', 'slug']),
            'subcategories' => $category->children()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['name', 'slug']),
            'products' => ProductCards::query($sort)
                ->whereIn('category_id', $category->selfAndActiveDescendantIds())
                ->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (Product $product) => ProductCards::toCard($product)),
            'sort' => $sort,
        ]);
    }

    public function search(Request $request): Response
    {
        // Same length cap as product names; longer input can't match anyway.
        $q = mb_substr(trim($request->string('q')->value()), 0, 200);
        $sort = $this->sort($request);

        return Inertia::render('shop/search', [
            'q' => $q,
            'sort' => $sort,
            // Nothing typed: no query at all, the page shows categories.
            'products' => $q === '' ? null : ProductCards::query($sort)
                ->where('name', 'ilike', Like::contains($q))
                ->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (Product $product) => ProductCards::toCard($product)),
            'categories' => $this->rootCategories(),
        ]);
    }

    /**
     * Unknown ?sort= values fall back to the default instead of erroring:
     * people edit URLs, and old links should keep working.
     */
    private function sort(Request $request): string
    {
        $sort = $request->string('sort')->value();

        return in_array($sort, ProductCards::SORTS, true) ? $sort : 'terbaru';
    }

    /**
     * @return list<array{name: string, slug: string}>
     */
    private function rootCategories(): array
    {
        /** @var list<array{name: string, slug: string}> */
        return Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->toArray();
    }
}
