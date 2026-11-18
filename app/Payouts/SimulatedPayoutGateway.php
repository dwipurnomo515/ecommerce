<?php

namespace App\Payouts;

use App\Models\Payout;

/**
 * Pretends to be a bank transfer service, so the whole payout flow can be
 * tried without real money:
 *
 *   account number ending in 0000  → "rekening tidak ditemukan" (failed,
 *                                     money returns to the balance)
 *   account number ending in 9999  → service temporarily down (the job
 *                                     retries, like with a real outage)
 *   anything else                  → success, reference SIM-{id}
 *
 * Never allowed in production (see AppServiceProvider).
 */
class SimulatedPayoutGateway implements PayoutGateway
{
    public function transfer(Payout $payout): TransferResult
    {
        if (str_ends_with($payout->account_number, '9999')) {
            throw new PayoutUnavailable('Simulasi: layanan transfer sedang gangguan.');
        }

        if (str_ends_with($payout->account_number, '0000')) {
            return TransferResult::failed('Rekening tidak ditemukan (simulasi).');
        }

        // Deterministic per payout: calling twice gives the same reference,
        // as an idempotent real API would.
        return TransferResult::succeeded('SIM-'.str_pad((string) $payout->id, 8, '0', STR_PAD_LEFT));
    }
}
