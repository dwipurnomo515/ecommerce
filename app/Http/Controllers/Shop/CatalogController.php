<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Like;
use App\Support\ProductCards;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public browsing: home, category pages and search. All product lists go
 * through ProductCards (and so through Product::visibleToBuyers()).
 */
class CatalogController extends Controller
{
    private const PER_PAGE = 24;

    private const TYPO_THRESHOLD = 0.4;

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

        $sort = ProductCards::sortFrom($request);

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
        // Search results are best ordered by how well they match; the
        // other orders stay available.
        $sort = in_array($request->string('sort')->value(), ['', 'relevan'], true) ? 'relevan' : ProductCards::sortFrom($request);

        return Inertia::render('shop/search', [
            'q' => $q,
            'sort' => $sort,
            // Nothing typed: no query at all, the page shows categories.
            'products' => $q === '' ? null : $this->matching($q, $sort)
                ->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (Product $product) => ProductCards::toCard($product)),
            'categories' => $this->rootCategories(),
        ]);
    }

    /**
     * Exact substring OR close enough (typos), both served by the trigram
     * GIN index on products.name.
     *
     * @return Builder<Product>
     */
    private function matching(string $q, string $sort): Builder
    {
        // "<%" means "word similarity of the query inside the name is at
        // least the threshold". The default (0.6) misses real typos like
        // "kaoss polso" (0.54); unrelated names score ~0.1. Measured on
        // our own data before picking 0.4. Session-level, constant value.
        DB::statement('SET pg_trgm.word_similarity_threshold = '.self::TYPO_THRESHOLD);

        $query = ProductCards::query($sort === 'relevan' ? 'terbaru' : $sort)
            ->where(fn (Builder $w) => $w
                ->where('products.name', 'ilike', Like::contains($q))
                ->orWhereRaw('? <% products.name', [$q]));

        if ($sort === 'relevan') {
            // Best match first; newest wins a tie (and id keeps pages stable).
            $query->reorder()
                ->orderByRaw('word_similarity(?, products.name) desc', [$q])
                ->orderByDesc('products.created_at')
                ->orderByDesc('products.id');
        }

        return $query;
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
