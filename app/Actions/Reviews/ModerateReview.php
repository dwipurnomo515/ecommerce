<?php

namespace App\Actions\Reviews;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hiding a review takes it out of the product's and the store's averages;
 * showing it again puts it back. The totals move with ONE atomic UPDATE
 * each (like when the review was written), in the same transaction as
 * the review and its audit line.
 */
class ModerateReview
{
    public function hide(Review $review, string $reason): Review
    {
        return DB::transaction(function () use ($review, $reason): Review {
            $locked = Review::query()->lockForUpdate()->findOrFail($review->id);

            if ($locked->hidden_at !== null) {
                throw ValidationException::withMessages(['reason' => 'Ulasan ini sudah disembunyikan.']);
            }

            $locked->forceFill(['hidden_at' => now(), 'hidden_reason' => $reason])->save();
            $this->moveTotals($locked, -1);

            AuditLog::record('review.hidden', $locked, "Ulasan #{$locked->id}", [
                'reason' => $reason,
                'rating' => $locked->rating,
            ]);

            return $locked;
        });
    }

    public function show(Review $review): Review
    {
        return DB::transaction(function () use ($review): Review {
            $locked = Review::query()->lockForUpdate()->findOrFail($review->id);

            if ($locked->hidden_at === null) {
                return $locked; // already visible: nothing to undo
            }

            $previousReason = $locked->hidden_reason;
            $locked->forceFill(['hidden_at' => null, 'hidden_reason' => null])->save();
            $this->moveTotals($locked, +1);

            AuditLog::record('review.unhidden', $locked, "Ulasan #{$locked->id}", ['previous_reason' => $previousReason]);

            return $locked;
        });
    }

    /** $direction: -1 to take the review out of the averages, +1 to put it back. */
    private function moveTotals(Review $review, int $direction): void
    {
        $change = ['rating_count' => $direction, 'rating_sum' => $direction * $review->rating];

        Product::query()->whereKey($review->product_id)->incrementEach($change);
        Store::query()->whereKey($review->store_id)->incrementEach($change);
    }
}
