<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of a store's money book. Append-only: see the migration.
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $order_id
 * @property string $type
 * @property int $amount Signed whole Rupiah
 * @property int $balance_after
 * @property string $description
 * @property CarbonImmutable $created_at
 */
class LedgerEntry extends Model
{
    public const ORDER_INCOME = 'order_income';

    public const COMMISSION = 'commission';

    protected $table = 'store_ledger_entries';

    /** Only created_at: an entry is never updated. */
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        // Append-only in code too: changing history must be impossible
        // by accident. Corrections are new entries.
        static::updating(fn () => throw new LogicException('Ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are append-only.'));
    }

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
            'amount' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
