<?php

namespace App\Actions\Orders;

use App\Checkout\ReturnStock;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Order;
use App\Refunds\RequestRefund;
use Illuminate\Support\Facades\DB;

/**
 * The seller can't fulfil a paid order (out of stock after all, damaged
 * item...). One transaction: order cancelled + stock back + refund queued,
 * or none of it.
 *
 * The seller's balance is untouched: an order only pays the store when it
 * completes (escrow), so there is nothing to take back.
 */
class CancelPaidOrder
{
    public function __construct(
        private readonly ReturnStock $returnStock,
        private readonly RequestRefund $requestRefund,
    ) {}

    /**
     * @throws InvalidStatusTransition when it already shipped, completed or was cancelled
     */
    public function handle(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->status->canTransitionTo(OrderStatus::Cancelled)) {
                throw InvalidStatusTransition::from($locked->status, OrderStatus::Cancelled);
            }

            $locked->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ])->save();

            $this->returnStock->forOrders([$locked->id]);
            $this->requestRefund->forOrder($locked, "Dibatalkan penjual: {$reason}");

            return $locked;
        });
    }
}
