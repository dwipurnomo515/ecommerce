<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Checkout;
use App\Payments\ReconcilePayment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:sync')]
#[Description('Ask the payment provider about pending checkouts whose webhook may have been missed')]
class SyncPayments extends Command
{
    public function handle(ReconcilePayment $reconcile): int
    {
        $updated = 0;

        Checkout::query()
            ->where('payment_status', PaymentStatus::Pending)
            ->whereNotNull('payment_token')
            ->chunkById(50, function ($checkouts) use ($reconcile, &$updated): void {
                foreach ($checkouts as $checkout) {
                    $outcome = $reconcile->handle($checkout, everySeconds: 60);
                    $updated += (int) in_array($outcome, ['marked_paid', 'cancelled', 'needs_refund'], true);
                }
            });

        $this->info("{$updated} pembayaran diperbarui dari Midtrans.");

        return self::SUCCESS;
    }
}
