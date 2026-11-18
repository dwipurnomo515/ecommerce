<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * WHO may act on an order. (Which step is allowed NOW lives in
 * OrderStatus::canTransitionTo.)
 */
class OrderPolicy
{
    /** The store that sells it: process and ship. */
    public function fulfil(User $user, Order $order): bool
    {
        return $user->store !== null && $order->store_id === $user->store->id;
    }

    /** The buyer who ordered it: confirm receipt. */
    public function confirmReceipt(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
