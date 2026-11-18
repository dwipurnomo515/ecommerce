<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /** Only the store whose product was reviewed. */
    public function reply(User $user, Review $review): bool
    {
        return $user->store !== null && $review->store_id === $user->store->id;
    }
}
