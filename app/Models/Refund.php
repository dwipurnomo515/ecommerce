<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Not fillable: written by RequestRefund and ProcessRefund only.
 *
 * @property int $id
 * @property int $checkout_id
 * @property int|null $order_id null = the whole checkout
 * @property int $amount
 * @property string $reason
 * @property string $status pending | refunded | failed
 * @property string|null $reference
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Refund extends Model
{
    public const PENDING = 'pending';

    public const REFUNDED = 'refunded';

    public const FAILED = 'failed';

    /**
     * @return BelongsTo<Checkout, $this>
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    public function label(): string
    {
        return match ($this->status) {
            self::PENDING => 'Refund diproses',
            self::REFUNDED => 'Dana dikembalikan',
            self::FAILED => 'Refund gagal, tim Zetago menghubungi Anda',
            default => $this->status,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
