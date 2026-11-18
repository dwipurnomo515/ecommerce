<?php

namespace App\Refunds;

use App\Models\Refund;
use App\Payouts\TransferResult;

/** Always succeeds, with a deterministic reference. Never in production. */
class SimulatedRefundGateway implements RefundGateway
{
    public function refund(Refund $refund): TransferResult
    {
        return TransferResult::succeeded('SIMREF-'.str_pad((string) $refund->id, 8, '0', STR_PAD_LEFT));
    }
}
