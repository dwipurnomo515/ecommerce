<?php

namespace App\Jobs;

use App\Ledger\Ledger;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Payouts\PayoutGateway;
use App\Payouts\PayoutUnavailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one payout through the gateway, in the background: a bank
 * transfer can take seconds, and the seller shouldn't wait on the page.
 */
class ProcessPayout implements ShouldQueue
{
    use Queueable;

    /** Temporary outages are retried: after 30 s, 1, 2 and 5 minutes. */
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(public readonly int $payoutId) {}

    public function handle(PayoutGateway $gateway, Ledger $ledger): void
    {
        $payout = Payout::query()->find($this->payoutId);

        if ($payout === null || $payout->status !== Payout::PENDING) {
            return; // already settled (a duplicate job, or a retry after success)
        }

        // Outside any DB lock: a real provider is an HTTP call that may take
        // seconds. PayoutUnavailable bubbles up → the queue retries.
        $result = $gateway->transfer($payout);

        DB::transaction(function () use ($payout, $result, $ledger): void {
            $locked = Payout::query()->lockForUpdate()->findOrFail($payout->id);

            if ($locked->status !== Payout::PENDING) {
                return;
            }

            $locked->processed_at = now();

            if ($result->succeeded) {
                $locked->status = Payout::PAID;
                $locked->reference = $result->reference;
                $locked->save();

                // Actor: the system (this runs in the queue worker).
                AuditLog::record('payout.paid', $locked, "Payout #{$locked->id}",
                    ['amount' => $locked->amount, 'reference' => $result->reference]);

                return;
            }

            $locked->status = Payout::FAILED;
            $locked->failure_reason = $result->failureReason;
            $locked->save();

            AuditLog::record('payout.failed', $locked, "Payout #{$locked->id}",
                ['amount' => $locked->amount, 'reason' => $result->failureReason]);

            // The money comes back as a NEW, opposite line: history keeps
            // both the withdrawal and its reversal.
            $ledger->post($locked->store_id, null, LedgerEntry::PAYOUT_REVERSAL, $locked->amount,
                'Penarikan gagal, dana dikembalikan: '.$locked->failure_reason, $locked);
        });
    }

    /**
     * All retries used up while the provider was unreachable. We do NOT
     * reverse the money automatically: with a real provider we can't be
     * sure the transfer didn't happen. A human checks with the provider.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Payout stuck after retries; needs a manual check', [
            'payout_id' => $this->payoutId,
            'error' => $e->getMessage(),
            'temporary' => $e instanceof PayoutUnavailable,
        ]);
    }
}
