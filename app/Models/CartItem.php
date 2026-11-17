<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $product_variant_id
 * @property int $quantity
 * @property int|null $seen_price Only for "the price changed" notices, never for billing.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
// user_id is not fillable: rows are created through $user->cartItems().
#[Fillable(['product_variant_id', 'quantity'])]
class CartItem extends Model
{
    /** Per variant, per order. Stops typos like 1000 and silly bulk buys. */
    public const MAX_QUANTITY = 99;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'seen_price' => 'integer',
        ];
    }
}
