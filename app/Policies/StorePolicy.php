<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

/**
 * WHO may do something with a store. (WHAT is allowed given the store's
 * current status lives in StoreStatus::canTransitionTo.)
 *
 * Laravel finds this class automatically: model Store → StorePolicy.
 */
class StorePolicy
{
    // --- Sellers -----------------------------------------------------------

    /**
     * One store per user (also enforced by a unique index on stores.user_id).
     */
    public function create(User $user): bool
    {
        return $user->store()->doesntExist();
    }

    public function update(User $user, Store $store): bool
    {
        return $user->id === $store->user_id;
    }

    // --- Admins ------------------------------------------------------------

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
