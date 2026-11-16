<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Any seller can see their own product list (even while the store is
     * still under review, so the page can explain why it is empty).
     */
    public function viewAny(User $user): bool
    {
        return $user->store()->exists();
    }

    /**
     * Only an approved (active) store may add products.
     */
    public function create(User $user): bool
    {
        return $user->store?->isActive() ?? false;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->store !== null && $product->store_id === $user->store->id;
    }
}
