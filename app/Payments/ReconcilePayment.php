<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Checkout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Webhooks get lost (our server was down, the tunnel closed, the provider
 * had an outage). So for a checkout that is still "pending" but has a
 * payment started, we ASK the provider and apply the answer through the
 * same rules as a webhook.
 */
class ReconcilePayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly HandlePaymentNotification $apply,
    ) {}

    /**
     * @param  int  $everySeconds  don't ask about the same checkout more often
     *                             than this (page reloads, overlapping runs)
     * @return string|null the outcome, or null when nothing was asked
     */
    public function handle(Checkout $checkout, int $everySeconds = 30): ?string
    {
        // Only a checkout that is waiting AND for which the buyer opened the
        // payment popup can have a payment we don't know about.
        if ($checkout->payment_status !== PaymentStatus::Pending || $checkout->payment_token === null) {
            return null;
        }

        // Cache::add is atomic: only the first caller in the window gets
        // true. Protects the provider (and our page speed) from a buyer
        // hammering F5.
        if ($everySeconds > 0 && ! Cache::add("payment-check:{$checkout->id}", true, $everySeconds)) {
            return null;
        }

        try {
            $status = $this->gateway->fetchStatus($checkout->code);
        } catch (PaymentUnavailable $e) {
            // Not fatal: the webhook or the next check will catch up.
            Log::warning('Payment status check failed', ['order_id' => $checkout->code, 'error' => $e->getMessage()]);

            return null;
        }

        if ($status === null) {
            return null;
        }

        $outcome = $this->apply->applyVerified($status, [
            'order_id' => $status->orderId,
            'transaction_status' => $status->rawStatus,
            'gross_amount' => $status->amount,
            'payment_type' => $status->method,
            'transaction_id' => $status->reference,
        ], 'status_check');

        $checkout->refresh();

        return $outcome;
    }
}
