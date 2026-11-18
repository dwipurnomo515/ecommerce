<?php

namespace App\Support;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The buyer's cart as it stands RIGHT NOW: today's prices and stock,
 * grouped per store (each store ships its own parcel). Checkout will use
 * the same rules, so "what the cart shows" and "what checkout accepts"
 * can't disagree.
 */
final class CartView
{
    public const OK = 'ok';

    /** Product hidden, deleted, banned, store suspended or variant removed. */
    public const UNAVAILABLE = 'unavailable';

    public const SOLD_OUT = 'sold_out';

    /** In stock, but less than the quantity in the cart. */
    public const NOT_ENOUGH = 'not_enough';

    /**
     * @return array{stores: array<int, array<string, mixed>>, total: int, count: int, has_problems: bool}
     */
    public static function for(User $user): array
    {
        $items = $user->cartItems()
            ->with([
                // withTrashed: a deleted product still shows its name, as
                // "no longer for sale", instead of silently vanishing.
                'variant.product' => fn ($q) => $q->withTrashed()->with([
                    'store:id,name,slug',
                    'images' => fn ($q) => $q->limit(1),
                ]),
            ])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->get();

        // One query, through THE visibility rule.
        $productIds = $items->map(fn (CartItem $i) => $i->variant->product_id)->unique()->values()->all();
        $visible = array_flip(Product::query()->visibleToBuyers()->whereKey($productIds)->pluck('id')->all());

        $stores = $items
            ->groupBy(fn (CartItem $i) => $i->variant->product->store_id)
            ->map(function (Collection $storeItems) use ($visible): array {
                /** @var Product $first */
                $first = $storeItems->first()->variant->product;
                $rows = $storeItems->map(fn (CartItem $i) => self::row($i, isset($visible[$i->variant->product_id])))->values();

                return [
                    'id' => $first->store->id,
                    'name' => $first->store->name,
                    'slug' => $first->store->slug,
                    'items' => $rows->all(),
                    // Only what can actually be bought counts.
                    'subtotal' => (int) $rows->where('status', self::OK)->sum(fn (array $r) => $r['price'] * $r['quantity']),
                ];
            })
            ->values();

        $rows = $stores->flatMap(fn (array $s) => $s['items']);

        return [
            'stores' => $stores->all(),
            'total' => (int) $stores->sum('subtotal'),
            'count' => (int) $rows->where('status', self::OK)->sum('quantity'),
            'has_problems' => $rows->contains(fn (array $r) => $r['status'] !== self::OK),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(CartItem $item, bool $visible): array
    {
        $variant = $item->variant;
        $product = $variant->product;

        $status = match (true) {
            ! $visible || ! $variant->is_active => self::UNAVAILABLE,
            $variant->stock === 0 => self::SOLD_OUT,
            $item->quantity > $variant->stock => self::NOT_ENOUGH,
            default => self::OK,
        };

        return [
            'id' => $item->id,
            'name' => $product->name,
            // No link to a product buyers can't open (it would 404).
            'href' => $visible ? route('shop.products.show', [$product->store->slug, $product->slug], absolute: false) : null,
            'imageUrl' => $product->images->first()?->thumbUrl(),
            'variant' => self::label($variant, $product),
            'price' => $variant->price,
            'compareAtPrice' => $variant->compare_at_price,
            // Set only when it changed since the buyer last saw it.
            'seenPrice' => $item->seen_price !== null && $item->seen_price !== $variant->price ? $item->seen_price : null,
            'quantity' => $item->quantity,
            'stock' => $variant->stock,
            'maxQuantity' => min($variant->stock, CartItem::MAX_QUANTITY),
            'status' => $status,
        ];
    }

    /**
     * "Hitam / L" in the seller's option order (jsonb sorts the keys).
     */
    private static function label(ProductVariant $variant, Product $product): string
    {
        return collect($product->options)
            ->map(fn (array $option) => $variant->options[$option['name']] ?? null)
            ->filter()
            ->implode(' / ');
    }
}
