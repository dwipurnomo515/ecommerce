<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets an item's quantity from the cart page (the stepper). Same stock
 * rule as AddToCart, but for an absolute number, not "add N".
 */
class ChangeCartQuantity
{
    public function handle(CartItem $item, int $quantity): CartItem
    {
        return DB::transaction(function () use ($item, $quantity): CartItem {
            $variant = ProductVariant::query()
                ->whereKey($item->product_variant_id)
                ->where('is_active', true)
                ->whereHas('product', fn ($q) => $q->visibleToBuyers())
                ->lockForUpdate()
                ->first();

            if ($variant === null) {
                throw ValidationException::withMessages(['quantity' => 'Produk ini sudah tidak dijual. Hapus dari keranjang.']);
            }

            if ($quantity > $variant->stock) {
                throw ValidationException::withMessages(['quantity' => $variant->stock === 0
                    ? 'Stok varian ini habis.'
                    : "Stok tersisa {$variant->stock}."]);
            }

            $item->quantity = $quantity;
            // The cart page showed today's price next to the stepper.
            $item->seen_price = $variant->price;
            $item->save();

            return $item;
        });
    }
}
