<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's review of one bought line. Not fillable: WriteReview sets
 * every column (the ids come from the order item, never from the form).
 *
 * @property int $id
 * @property int $order_item_id
 * @property int $product_id
 * @property int $store_id
 * @property int $user_id
 * @property int $rating 1-5
 * @property string|null $body
 * @property string|null $variant_label
 * @property string|null $seller_reply
 * @property CarbonImmutable|null $replied_at
 * @property CarbonImmutable|null $hidden_at Hidden by an admin: not shown, not counted
 * @property string|null $hidden_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Review extends Model
{
    /** How long after completion a buyer may still review. */
    public const WINDOW_DAYS = 60;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * "Budi Santoso" → "Budi S.": enough to feel human, not enough to
     * identify a buyer publicly.
     */
    public static function publicName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $first = $parts[0] ?? 'Pembeli';
        $initial = isset($parts[1]) ? ' '.mb_strtoupper(mb_substr($parts[1], 0, 1)).'.' : '';

        return $first.$initial;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'replied_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }
}
