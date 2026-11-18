<?php

namespace App\Refunds;

use App\Models\Refund;
use App\Payouts\PayoutUnavailable;
use App\Payouts\TransferResult;

/**
 * Sends money back to a buyer. Today: a simulation. Later: the payment
 * provider's refund API (it depends on how the buyer paid: cards refund
 * to the card, a virtual account needs the buyer's bank details).
 */
interface RefundGateway
{
    /**
     * Must be safe to call twice for the same refund (retried job).
     *
     * @throws PayoutUnavailable for temporary trouble (the job retries)
     */
    public function refund(Refund $refund): TransferResult;
}
