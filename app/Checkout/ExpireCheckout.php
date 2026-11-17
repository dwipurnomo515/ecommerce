<?php

namespace App\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Checkout;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * An unpaid checkout past its deadline: mark it expired, cancel its orders
 * and put the stock back on sale.
 *
 * Safe to run twice on the same checkout (two scheduler runs overlapping,
 * a retry after a crash): the checkout row is locked and re-checked, so
 * the stock is returned at most once.
 */
class ExpireCheckout
{
    /** @return bool true if this call expired it, false if there was nothing to do */
    public function handle(int $checkoutId): bool
    {
        return DB::transaction(function () use ($checkoutId): bool {
            // Locked re-read. In Tahap 5 the payment webhook takes the same
            // lock, so "paid at 23:59:59" and "expired" can't both win.
            $checkout = Checkout::query()->lockForUpdate()->find($checkoutId);

            if ($checkout === null
                || $checkout->payment_status !== PaymentStatus::Pending
                || $checkout->expires_at->isFuture()) {
                return false;
            }

            $this->cancelLocked($checkout);

            return true;
        });
    }

    /**
     * Cancels a pending checkout and returns its stock. The caller MUST
     * hold the row lock on $checkout inside a transaction. Also used when
     * the payment provider reports a failed payment.
     */
    public function cancelLocked(Checkout $checkout): void
    {
        $checkout->payment_status = PaymentStatus::Expired;
        $checkout->save();

        $orderIds = $checkout->orders()->pluck('id');
        $checkout->orders()->update(['status' => OrderStatus::Cancelled]);

        // Return stock per variant, locking in id order like PlaceOrder
        // does (same order everywhere = no deadlocks between the two).
        $returns = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->selectRaw('product_variant_id, sum(quantity) as quantity')
            ->groupBy('product_variant_id')
            ->orderBy('product_variant_id')
            ->pluck('quantity', 'product_variant_id');

        ProductVariant::query()->whereKey($returns->keys())->orderBy('id')->lockForUpdate()->get()
            ->each(fn (ProductVariant $v) => $v->increment('stock', (int) $returns[$v->id]));
    }
}
