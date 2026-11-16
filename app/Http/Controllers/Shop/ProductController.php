<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public storefront: no login needed.
 */
class ProductController extends Controller
{
    /**
     * /toko/{store-slug}/{product-slug}
     *
     * Plain slugs instead of route model binding: the lookup must go
     * through visibleToBuyers(), and a hidden product must give the SAME
     * 404 as one that never existed. A 403 would tell anyone that a draft
     * or banned product is there.
     */
    public function show(string $storeSlug, string $productSlug): Response
    {
        $product = Product::query()
            ->visibleToBuyers()
            ->whereRelation('store', 'slug', $storeSlug)
            ->where('slug', $productSlug)
            ->with([
                'store' => fn ($query) => $query->withCount(['products' => fn ($q) => $q->visibleToBuyers()]),
                'category',
                'images',
                'activeVariants' => fn ($query) => $query->orderBy('id'),
            ])
            ->firstOrFail();

        return Inertia::render('shop/product', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                // Search engines and link previews show ~155 characters.
                'summary' => Str::limit(Str::squish((string) $product->description), 155),
                'options' => $product->options,
                'images' => $product->images->map(fn (ProductImage $image) => [
                    'id' => $image->id,
                    'url' => $image->url(),
                ]),
                // Only what a buyer needs. SKU and weight stay private.
                'variants' => $product->activeVariants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'options' => (object) $variant->options,
                    'price' => $variant->price,
                    'compare_at_price' => $variant->compare_at_price,
                    'stock' => $variant->stock,
                ]),
            ],
            'categoryTrail' => $this->trail($product->category),
            'store' => [
                'name' => $product->store->name,
                'slug' => $product->store->slug,
                'joined_at' => $product->store->approved_at?->toIso8601String(),
                'products_count' => $product->store->products_count,
            ],
        ]);
    }

    /**
     * Root → … → the product's category, e.g. Fashion › Pria › Kaos.
     *
     * @return list<array{id: int, name: string}>
     */
    private function trail(Category $category): array
    {
        $ids = $category->ancestorIds();
        $ancestors = Category::query()->whereKey($ids)->get(['id', 'name'])->keyBy('id');

        $trail = [];
        // ancestorIds() goes parent → root; a breadcrumb reads root → leaf.
        foreach (array_reverse($ids) as $id) {
            $ancestor = $ancestors->get($id);
            if ($ancestor instanceof Category) {
                $trail[] = ['id' => $ancestor->id, 'name' => $ancestor->name];
            }
        }
        $trail[] = ['id' => $category->id, 'name' => $category->name];

        return $trail;
    }
}
