<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A withdrawal of store balance to its bank account. Not fillable:
 * written by RequestPayout and ProcessPayout only.
 *
 * @property int $id
 * @property int $store_id
 * @property int $amount
 * @property string $bank
 * @property string $account_number
 * @property string $account_name
 * @property string $status pending | paid | failed
 * @property string|null $reference
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Payout extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const MINIMUM = 50_000;

    /** Pending longer than this counts as stuck. */
    public const STUCK_AFTER_MINUTES = 60;

    /** Bank codes we accept, with display names. */
    public const BANKS = [
        'bca' => 'BCA',
        'bni' => 'BNI',
        'bri' => 'BRI',
        'mandiri' => 'Mandiri',
        'bsi' => 'BSI',
        'cimb' => 'CIMB Niaga',
        'permata' => 'Permata',
        'jago' => 'Bank Jago',
        'seabank' => 'SeaBank',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * What an admin should look at: stuck (pending for over an hour, so
     * the queue gave up or the worker is down) or failed this week. One
     * rule for the "Keuangan" page and the admin summary.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function needsAttention(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->where('status', self::PENDING)->where('created_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES)))
            ->orWhere(fn (Builder $q) => $q->where('status', self::FAILED)->where('created_at', '>=', now()->subDays(7))));
    }

    public function label(): string
    {
        return match ($this->status) {
            self::PENDING => 'Diproses',
            self::PAID => 'Berhasil',
            self::FAILED => 'Gagal',
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
