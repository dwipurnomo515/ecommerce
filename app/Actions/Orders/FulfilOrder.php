<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Ledger\Ledger;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * The fulfilment steps of an order. WHO may take a step is the policy's
 * job; WHETHER the step is allowed from the current status is decided
 * here, on the locked row, with OrderStatus::canTransitionTo().
 */
class FulfilOrder
{
    public function __construct(private readonly Ledger $ledger) {}

    /** Seller: "Proses pesanan". */
    public function process(Order $order): Order
    {
        return $this->move($order, OrderStatus::Processing, function (Order $o): void {
            $o->processed_at = now();
        });
    }

    /** Seller: "Kirim", with the courier's tracking number. */
    public function ship(Order $order, string $trackingNumber): Order
    {
        return $this->move($order, OrderStatus::Shipped, function (Order $o) use ($trackingNumber): void {
            $o->tracking_number = $trackingNumber;
            $o->shipped_at = now();
        });
    }

    /**
     * Buyer: "Pesanan diterima" (or the auto-complete job). The store is
     * paid at this moment, not at payment: the money is held until the
     * parcel is confirmed (escrow). Same transaction: completed AND
     * credited, or neither.
     */
    public function complete(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $completed = $this->move($order, OrderStatus::Completed, function (Order $o): void {
                $o->completed_at = now();
            });

            $this->ledger->creditCompletedOrder($completed);

            return $completed;
        });
    }

    /**
     * @param  callable(Order): void  $fill
     *
     * @throws InvalidStatusTransition when the order isn't in the right state
     *                                 (e.g. the buyer and the auto-complete
     *                                 job acted at the same moment)
     */
    private function move(Order $order, OrderStatus $to, callable $fill): Order
    {
        return DB::transaction(function () use ($order, $to, $fill): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->status->canTransitionTo($to)) {
                throw InvalidStatusTransition::from($locked->status, $to);
            }

            $locked->status = $to;
            $fill($locked);
            $locked->save();

            return $locked;
        });
    }
}
