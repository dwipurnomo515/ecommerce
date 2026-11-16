<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

/**
 * WHO may do something with a store. (WHAT is allowed given the store's
 * current status lives in StoreStatus::canTransitionTo.)
 *
 * Laravel finds this class automatically: model Store → StorePolicy.
 * Seller abilities (update own store, ...) will be added in the seller step.
 */
class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function approve(User $user, Store $store): bool
    {
        return $user->isAdmin();
    }

    public function suspend(User $user, Store $store): bool
    {
        return $user->isAdmin();
    }
}
