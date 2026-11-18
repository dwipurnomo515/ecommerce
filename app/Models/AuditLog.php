<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string $subject_type
 * @property int $subject_id
 * @property string $subject_label
 * @property array<string, mixed>|null $details
 * @property string|null $ip
 * @property CarbonImmutable $created_at
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** Readable names for the admin page. */
    public const LABELS = [
        'product.banned' => 'Memblokir produk',
        'product.unbanned' => 'Membuka blokir produk',
        'store.approved' => 'Menyetujui toko',
        'store.suspended' => 'Menangguhkan toko',
        'order.cancelled_by_seller' => 'Penjual membatalkan pesanan',
        'store.bank_account_changed' => 'Mengubah rekening pencairan',
        'payout.requested' => 'Mengajukan penarikan',
        'payout.paid' => 'Penarikan berhasil',
        'payout.failed' => 'Penarikan gagal',
        'payout.retried' => 'Mencoba ulang penarikan',
        'refund.retried' => 'Mencoba ulang refund',
        'user.admin_granted' => 'Memberi hak admin',
        'user.admin_revoked' => 'Mencabut hak admin',
    ];

    protected static function booted(): void
    {
        // History is not editable: not by mistake, not by a compromised
        // admin trying to cover their tracks through the app.
        static::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }

    /**
     * Writes one entry. Call it INSIDE the transaction of the action: if the
     * action rolls back, so does its log line, so the log never claims
     * something that didn't happen.
     *
     * @param  array<string, mixed>  $details  never full secrets (mask bank numbers)
     */
    public static function record(string $action, Model $subject, string $label, array $details = []): self
    {
        $log = new self;
        $log->forceFill([
            // The logged-in user, or null when the system acts (jobs,
            // scheduler, console).
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => strtolower(class_basename($subject)),
            'subject_id' => $subject->getKey(),
            'subject_label' => mb_substr($label, 0, 200),
            'details' => $details === [] ? null : $details,
            // Null for console commands and queue jobs (no remote address).
            // Not runningInConsole(): that is also true while tests send
            // simulated HTTP requests.
            'ip' => request()->ip(),
            'created_at' => now(),
        ])->save();

        return $log;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
