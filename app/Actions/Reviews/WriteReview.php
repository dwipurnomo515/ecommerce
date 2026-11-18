<?php

namespace App\Actions\Reviews;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WriteReview
{
    /**
     * Why this buyer may not review this line right now, or null if they
     * may. Shared by the page (to show the button) and by handle().
     */
    public function refusal(User $user, OrderItem $item): ?string
    {
        $order = $item->order;

        return match (true) {
            $order->user_id !== $user->id => 'Anda hanya bisa mengulas barang yang Anda beli.',
            $order->status !== OrderStatus::Completed => 'Ulasan bisa ditulis setelah pesanan selesai.',
            $order->completed_at === null
                || $order->completed_at->addDays(Review::WINDOW_DAYS)->isPast() => 'Batas waktu menulis ulasan sudah lewat.',
            // Eager-loaded on list pages; one query for a single item.
            $item->review !== null => 'Anda sudah mengulas barang ini.',
            default => null,
        };
    }

    /**
     * @throws ValidationException under "review"
     */
    public function handle(User $user, OrderItem $item, int $rating, ?string $body): Review
    {
        if ($reason = $this->refusal($user, $item)) {
            throw ValidationException::withMessages(['review' => $reason]);
        }

        try {
            return DB::transaction(function () use ($user, $item, $rating, $body): Review {
                $review = new Review;
                $review->forceFill([
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'store_id' => $item->order->store_id,
                    'user_id' => $user->id,
                    'rating' => $rating,
                    'body' => $body,
                    'variant_label' => $item->variant_label,
                ])->save();

                // ONE atomic UPDATE: rating_count = rating_count + 1, ...
                // Reading the totals, adding in PHP and writing them back
                // would lose a review if two arrived at the same moment.
                Product::query()->whereKey($item->product_id)->incrementEach([
                    'rating_count' => 1,
                    'rating_sum' => $rating,
                ]);

                return $review;
            });
        } catch (UniqueConstraintViolationException) {
            // A double submit raced past refusal(): the unique index on
            // order_item_id let only the first one in.
            throw ValidationException::withMessages(['review' => 'Anda sudah mengulas barang ini.']);
        }
    }
}
