<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bought line, frozen: name, variant, price and weight as they were
 * when the order was placed.
 *
 * @property int $id
 * @property int $order_id
 * @property int $product_variant_id
 * @property int $product_id
 * @property string $product_name
 * @property string|null $variant_label
 * @property string|null $sku
 * @property int $price
 * @property int $quantity
 * @property int $weight_grams
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class OrderItem extends Model
{
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
            'weight_grams' => 'integer',
        ];
    }
}
