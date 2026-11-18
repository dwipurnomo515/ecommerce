<?php

namespace App\Console\Commands;

use App\Checkout\ExpireCheckout;
use App\Enums\PaymentStatus;
use App\Models\Checkout;
use App\Payments\ReconcilePayment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire')]
#[Description('Cancel unpaid checkouts past their payment deadline and return their stock')]
class ExpireCheckouts extends Command
{
    public function handle(ExpireCheckout $expire, ReconcilePayment $reconcile): int
    {
        $expired = 0;

        // In batches: never load every overdue checkout at once. Each one
        // is then expired in its own small transaction, so one problem
        // doesn't roll back (or lock) all the others.
        Checkout::query()
            ->where('payment_status', PaymentStatus::Pending)
            ->where('expires_at', '<=', now())
            ->select(['id', 'code', 'payment_status', 'payment_token'])
            ->chunkById(100, function ($checkouts) use ($expire, $reconcile, &$expired): void {
                foreach ($checkouts as $checkout) {
                    // Last chance: if the buyer DID pay and only the webhook
                    // got lost, record the payment instead of cancelling a
                    // paid order (which would mean a refund).
                    $reconcile->handle($checkout, everySeconds: 0);

                    // Re-checks "still pending" under the row lock.
                    $expired += (int) $expire->handle($checkout->id);
                }
            });

        $this->info("{$expired} checkout kedaluwarsa dibatalkan.");

        return self::SUCCESS;
    }
}
