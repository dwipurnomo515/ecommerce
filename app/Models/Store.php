<?php

namespace App\Models;

use App\Enums\StoreStatus;
use App\Models\Concerns\HasRatingTotals;
use App\Shipping\Destination;
use Carbon\CarbonImmutable;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $logo_path
 * @property int|null $origin_destination_id RajaOngkir destination the store ships from
 * @property string|null $origin_label
 * @property string|null $origin_city
 * @property StoreStatus $status
 * @property int $rating_count Reviews of all its products (running total)
 * @property int $rating_sum
 * @property int $balance Current money balance; changed only through Ledger::post()
 * @property string|null $payout_bank
 * @property string|null $payout_account_number
 * @property string|null $payout_account_name
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
// Not fillable on purpose: user_id (set via $user->store()->create()),
// status and approved_at (changed only by admin actions, never by form input).
#[Fillable(['name', 'slug', 'description', 'logo_path'])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, HasRatingTotals;

    /**
     * Mirrors the DB default so a freshly created model knows its status
     * without being re-fetched.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'rating_count' => 0,
        'rating_sum' => 0,
        'balance' => 0,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isActive(): bool
    {
        return $this->status === StoreStatus::Active;
    }

    /** Without an origin, shipping can't be priced, so checkout must wait. */
    public function hasShippingOrigin(): bool
    {
        return $this->origin_destination_id !== null;
    }

    /**
     * Set only from a decrypted Destination token (see StoreProfileRequest),
     * never from raw form fields, so the columns are not fillable.
     */
    public function setShippingOrigin(Destination $origin): void
    {
        $this->origin_destination_id = $origin->id;
        $this->origin_label = $origin->label;
        $this->origin_city = $origin->city;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'approved_at' => 'datetime',
            'rating_count' => 'integer',
            'rating_sum' => 'integer',
            'balance' => 'integer',
        ];
    }
}
