<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Puts a variant in the buyer's cart, or raises the quantity if it is
 * already there.
 *
 * The cart does NOT reserve stock: five people may each have the last
 * item in their cart. Stock is checked here (so nobody adds 10 of 3) and
 * then taken for real, atomically, at checkout.
 */
class AddToCart
{
    /**
     * @throws ValidationException with a message for the buyer
     */
    public function handle(User $user, int $variantId, int $quantity): CartItem
    {
        return DB::transaction(function () use ($user, $variantId, $quantity): CartItem {
            // Lock the variant row: a fast double-click sends two requests;
            // the second waits here and then sees the first one's cart row,
            // instead of both inserting (unique violation) or both passing
            // the stock check.
            $variant = ProductVariant::query()
                ->whereKey($variantId)
                ->where('is_active', true)
                ->whereHas('product', fn ($q) => $q->visibleToBuyers())
                ->with('product:id,store_id')
                ->lockForUpdate()
                ->first();

            if ($variant === null) {
                // Same message for "never existed" and "hidden": no leaks.
                $this->fail('variant_id', 'Produk ini sudah tidak dijual.');
            }

            /** @var Product $product */
            $product = $variant->product;

            if ($product->store_id === $user->store?->id) {
                $this->fail('variant_id', 'Ini produk toko Anda sendiri.');
            }

            $item = $user->cartItems()->firstOrNew(['product_variant_id' => $variant->id]);
            $inCart = $item->exists ? $item->quantity : 0;
            $total = $inCart + $quantity;

            if ($total > CartItem::MAX_QUANTITY) {
                $this->fail('quantity', 'Maksimal '.CartItem::MAX_QUANTITY." per varian. Di keranjang Anda sudah ada {$inCart}.");
            }

            if ($total > $variant->stock) {
                $this->fail('quantity', $variant->stock === 0
                    ? 'Stok varian ini habis.'
                    : "Stok tersisa {$variant->stock}".($inCart > 0 ? ", dan {$inCart} sudah ada di keranjang Anda." : '.'));
            }

            $item->quantity = $total;
            // The buyer just saw the product page at this price.
            $item->seen_price = $variant->price;
            $item->save();

            return $item;
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
