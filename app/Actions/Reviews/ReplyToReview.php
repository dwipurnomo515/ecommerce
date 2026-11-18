<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The seller answers a review, once. Not editable afterwards: buyers
 * reading the page should see what was said, not a quietly reworded
 * version after an argument.
 */
class ReplyToReview
{
    /**
     * @throws ValidationException under "reply" when already answered
     */
    public function handle(Review $review, string $reply): Review
    {
        return DB::transaction(function () use ($review, $reply): Review {
            // Locked: two tabs replying at once can't both "win".
            $locked = Review::query()->lockForUpdate()->findOrFail($review->id);

            if ($locked->seller_reply !== null) {
                throw ValidationException::withMessages(['reply' => 'Ulasan ini sudah Anda balas.']);
            }

            $locked->seller_reply = $reply;
            $locked->replied_at = now();
            $locked->save();

            return $locked;
        });
    }
}
