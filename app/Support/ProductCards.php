<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Everything a storefront grid needs, in one place: which products (only
 * visibleToBuyers), what to load (no N+1), how to sort, and the exact
 * shape the React ProductCard expects.
 */
final class ProductCards
{
    public const SORTS = ['terbaru', 'termurah', 'termahal'];

    /**
     * ?sort= from the URL. Unknown values fall back to the default instead
     * of erroring: people edit URLs, and old links should keep working.
     */
    public static function sortFrom(Request $request): string
    {
        $sort = $request->string('sort')->value();

        return in_array($sort, self::SORTS, true) ? $sort : 'terbaru';
    }

    /**
     * @return Builder<Product>
     */
    public static function query(string $sort = 'terbaru'): Builder
    {
        $query = Product::query()
            ->visibleToBuyers()
            ->with([
                'store:id,name,slug',
                // Only the cover photo per product (one window query).
                'images' => fn ($q) => $q->limit(1),
                // Few columns: a grid of 24 products × their variants.
                'activeVariants:id,product_id,price,compare_at_price,stock',
            ]);

        return match ($sort) {
            // A product "costs" its cheapest variant, as on the card.
            'termurah' => $query->withMin(['variants' => fn ($q) => $q->where('is_active', true)], 'price')
                ->orderBy('variants_min_price')->orderByDesc('id'),
            'termahal' => $query->withMin(['variants' => fn ($q) => $q->where('is_active', true)], 'price')
                ->orderByDesc('variants_min_price')->orderByDesc('id'),
            // id as the tie-breaker: without a unique last sort key, rows
            // with equal values can swap between pages and appear twice.
            default => $query->latest()->orderByDesc('id'),
        };
    }

    /**
     * The shape of ProductCardData in resources/js/components/shop/product-card.tsx.
     *
     * @return array<string, mixed>
     */
    public static function toCard(Product $product): array
    {
        // Show the cheapest variant that can be bought; if all are sold
        // out, the cheapest overall (the card then says "Stok habis").
        $variants = $product->activeVariants->sortBy('price');
        /** @var ProductVariant $shown */
        $shown = $variants->first(fn (ProductVariant $v) => $v->stock > 0) ?? $variants->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'href' => route('shop.products.show', [$product->store->slug, $product->slug], absolute: false),
            'imageUrl' => $product->images->first()?->url(),
            'price' => $shown->price,
            'compareAtPrice' => $shown->compare_at_price,
            'inStock' => $shown->stock > 0,
            'store' => ['name' => $product->store->name],
        ];
    }
}
