<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One store's part of a checkout: shipped as one parcel.
 * Not fillable: written only by PlaceOrder and later status actions.
 *
 * @property int $id
 * @property int $checkout_id
 * @property int $user_id
 * @property int $store_id
 * @property string $number
 * @property OrderStatus $status
 * @property int $items_total
 * @property int $shipping_cost
 * @property string $courier
 * @property string $courier_service
 * @property string|null $shipping_etd
 * @property int $weight_grams
 * @property string $recipient_name
 * @property string $phone
 * @property string $street
 * @property string|null $address_notes
 * @property string $province
 * @property string $city
 * @property string $district
 * @property string $subdistrict
 * @property string $postal_code
 * @property int $destination_id
 * @property int $origin_destination_id
 * @property int $commission_bps Rate when placed, in basis points (500 = 5 %)
 * @property string|null $tracking_number
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $shipped_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Order extends Model
{
    /** Days a buyer has to confirm receipt before it completes by itself. */
    public const AUTO_COMPLETE_DAYS = 7;

    /**
     * Zetago's share of the item price, rounded DOWN: integer division,
     * no floats. Any fraction of a Rupiah stays with the seller.
     */
    public function commission(): int
    {
        return intdiv($this->items_total * $this->commission_bps, 10_000);
    }

    /** What the store earns: items + shipping (it pays the courier) − commission. */
    public function sellerEarning(): int
    {
        return $this->items_total + $this->shipping_cost - $this->commission();
    }

    /**
     * @return BelongsTo<Checkout, $this>
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'items_total' => 'integer',
            'shipping_cost' => 'integer',
            'commission_bps' => 'integer',
            'weight_grams' => 'integer',
            'processed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
