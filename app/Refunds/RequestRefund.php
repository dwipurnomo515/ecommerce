<?php

namespace App\Refunds;

use App\Jobs\ProcessRefund;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Records that money must go back to the buyer, and queues the transfer.
 * Call inside the transaction that cancels the order: the refund exists
 * if and only if the cancellation happened.
 */
class RequestRefund
{
    /** One store's order, cancelled by the seller after payment. */
    public function forOrder(Order $order, string $reason): Refund
    {
        return $this->create($order->checkout_id, $order->id, $order->items_total + $order->shipping_cost, $reason);
    }

    /** A whole checkout paid after it had been cancelled. */
    public function forCheckout(Checkout $checkout, string $reason): Refund
    {
        return $this->create($checkout->id, null, $checkout->grand_total, $reason);
    }

    private function create(int $checkoutId, ?int $orderId, int $amount, string $reason): Refund
    {
        $refund = new Refund;
        $refund->forceFill([
            'checkout_id' => $checkoutId,
            'order_id' => $orderId,
            'amount' => $amount,
            'reason' => $reason,
            'status' => Refund::PENDING,
        ]);

        // Throws UniqueConstraintViolationException if this money was
        // already refunded: never twice (refunds_once_per_* indexes).
        $refund->save();

        ProcessRefund::dispatch($refund->id)->afterCommit();

        return $refund;
    }
}
