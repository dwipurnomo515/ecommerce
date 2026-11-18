<?php

namespace App\Checkout;

use App\Models\OrderItem;
use App\Models\ProductVariant;

/**
 * Puts the stock of cancelled orders back on sale. Used by expiry, failed
 * payments and seller cancellations. Call inside a transaction.
 */
class ReturnStock
{
    /**
     * @param  iterable<int>  $orderIds
     */
    public function forOrders(iterable $orderIds): void
    {
        $returns = OrderItem::query()
            ->whereIn('order_id', collect($orderIds))
            ->selectRaw('product_variant_id, sum(quantity) as quantity')
            ->groupBy('product_variant_id')
            ->pluck('quantity', 'product_variant_id');

        // Locked in id order, like PlaceOrder: same order everywhere means
        // two transactions can never wait for each other forever.
        ProductVariant::query()->whereKey($returns->keys())->orderBy('id')->lockForUpdate()->get()
            ->each(fn (ProductVariant $v) => $v->increment('stock', (int) $returns[$v->id]));
    }
}
