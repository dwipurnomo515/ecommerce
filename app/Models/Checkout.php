<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One purchase and its one payment, covering one order per store.
 *
 * Nothing here is fillable: every column is set by PlaceOrder (or, later,
 * the payment webhook), never from request data.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code
 * @property string $idempotency_key
 * @property PaymentStatus $payment_status
 * @property int $items_total
 * @property int $shipping_total
 * @property int $grand_total
 * @property CarbonImmutable $expires_at
 * @property string|null $payment_token Midtrans Snap token, created on the first "Bayar"
 * @property CarbonImmutable|null $paid_at
 * @property string|null $payment_method
 * @property string|null $payment_reference
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Checkout extends Model
{
    /** How long the buyer has to pay before the stock goes back on sale. */
    public const PAYMENT_WINDOW_HOURS = 24;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'items_total' => 'integer',
            'shipping_total' => 'integer',
            'grand_total' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
