<?php

namespace App\Payouts;

use App\Jobs\ProcessPayout;
use App\Ledger\Ledger;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The seller asks to withdraw. The balance is debited NOW, in the same
 * transaction that creates the payout: the same money can't be requested
 * twice, and the CHECK (balance >= 0) refuses more than is there. The
 * transfer itself happens later, in a queued job.
 */
class RequestPayout
{
    public function __construct(private readonly Ledger $ledger) {}

    /**
     * @throws ValidationException under "amount"
     */
    public function handle(Store $store, int $amount): Payout
    {
        if ($store->payout_account_number === null) {
            $this->fail('Isi rekening bank dulu.');
        }

        if ($amount < Payout::MINIMUM) {
            $this->fail('Penarikan minimal Rp '.number_format(Payout::MINIMUM, 0, ',', '.').'.');
        }

        try {
            $payout = DB::transaction(function () use ($store, $amount): Payout {
                $locked = Store::query()->lockForUpdate()->findOrFail($store->id);

                if ($amount > $locked->balance) {
                    $this->fail('Saldo tidak cukup.');
                }

                $payout = new Payout;
                $payout->forceFill([
                    'store_id' => $locked->id,
                    'amount' => $amount,
                    // A copy: editing the account later can't redirect this.
                    'bank' => $locked->payout_bank,
                    'account_number' => $locked->payout_account_number,
                    'account_name' => $locked->payout_account_name,
                    'status' => Payout::PENDING,
                ])->save();

                $bank = Payout::BANKS[$payout->bank] ?? strtoupper($payout->bank);
                $destination = "{$bank} ".self::masked($payout->account_number);
                $this->ledger->post($locked->id, null, LedgerEntry::PAYOUT, -$amount,
                    "Penarikan ke {$destination}", $payout);

                AuditLog::record('payout.requested', $payout, $locked->name, [
                    'amount' => $amount,
                    'to' => $destination, // masked: the log never holds a full account number
                ]);

                // afterCommit: the job must never run for a payout that was
                // rolled back (or before this transaction is visible).
                ProcessPayout::dispatch($payout->id)->afterCommit()->delay(now()->addSeconds(5));

                return $payout;
            });
        } catch (UniqueConstraintViolationException) {
            // payouts_one_pending_per_store
            $this->fail('Masih ada penarikan yang sedang diproses. Tunggu sampai selesai.');
        }

        return $payout;
    }

    /** "1234567890" → "••••7890": enough to recognise, not to copy. */
    public static function masked(string $accountNumber): string
    {
        return '••••'.substr($accountNumber, -4);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['amount' => $message]);
    }
}
