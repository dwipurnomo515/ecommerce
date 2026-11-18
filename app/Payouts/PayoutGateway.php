<?php

namespace App\Payouts;

use App\Models\Payout;

/**
 * Sends money to a bank account. Today: a simulation. Later: a real
 * disbursement API (Midtrans Iris, Xendit, Flip...) behind this same
 * interface, without touching the payout flow.
 */
interface PayoutGateway
{
    /**
     * Must be safe to call twice for the same payout (a retried job):
     * real providers take an idempotency key for exactly this; we pass
     * the payout id.
     *
     * @throws PayoutUnavailable for temporary trouble (the job retries)
     */
    public function transfer(Payout $payout): TransferResult;
}
