<?php

namespace App\Payments;

use App\Checkout\ExpireCheckout;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Checkout;
use App\Refunds\RequestRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a payment webhook to a checkout.
 *
 * Providers send the same notification more than once, and not always in
 * order (a "settlement" can arrive before its "pending"). So every case is
 * decided from the checkout's CURRENT status, under the same row lock the
 * expiry job uses:
 *
 *   paid    + pending checkout  → Paid (orders → Dibayar)
 *   paid    + already paid      → nothing (duplicate)
 *   paid    + already cancelled → NeedsRefund (stock is gone; a human refunds)
 *   failed  + pending checkout  → cancelled, stock returned
 *   failed  + paid              → nothing (stale/out-of-order)
 *   waiting / other             → nothing, just logged
 */
class HandlePaymentNotification
{
    public const IGNORED = 'ignored';

    public const MARKED_PAID = 'marked_paid';

    public const CANCELLED = 'cancelled';

    public const NEEDS_REFUND = 'needs_refund';

    public const BAD_SIGNATURE = 'bad_signature';

    public const AMOUNT_MISMATCH = 'amount_mismatch';

    public const UNKNOWN_ORDER = 'unknown_order';

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ExpireCheckout $expire,
        private readonly RequestRefund $requestRefund,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  the raw webhook body
     * @return string one of the constants above (also stored in the log)
     */
    public function handle(array $payload): string
    {
        $notification = $this->gateway->parseNotification($payload);

        if ($notification === null) {
            $this->log($payload, null, self::BAD_SIGNATURE, signatureValid: false, source: 'webhook');
            Log::warning('Payment webhook with an invalid signature', ['order_id' => $payload['order_id'] ?? null]);

            return self::BAD_SIGNATURE;
        }

        return $this->applyVerified($notification, $payload, 'webhook');
    }

    /**
     * Applies news that is already known to come from the provider: a
     * webhook whose signature checked out, or the answer to our own status
     * request. Both go through exactly the same rules.
     *
     * @param  array<string, mixed>  $payload  raw data, for the audit log
     */
    public function applyVerified(PaymentNotification $notification, array $payload, string $source): string
    {
        return DB::transaction(function () use ($payload, $notification, $source): string {
            $checkout = Checkout::query()->where('code', $notification->orderId)->lockForUpdate()->first();

            $outcome = match (true) {
                $checkout === null => self::UNKNOWN_ORDER,
                // A correctly signed notice for a different amount means
                // something is off (a bug, or a tampered transaction
                // amount). Never mark it paid; flag it in the log.
                $notification->result === PaymentNotification::PAID
                    && $notification->amount !== $checkout->grand_total => self::AMOUNT_MISMATCH,
                default => $this->apply($checkout, $notification),
            };

            $this->log($payload, $checkout, $outcome, signatureValid: true, source: $source);

            if ($outcome === self::AMOUNT_MISMATCH || $outcome === self::NEEDS_REFUND) {
                Log::error("Payment needs attention: {$outcome}", ['order_id' => $notification->orderId]);
            }

            return $outcome;
        });
    }

    private function apply(Checkout $checkout, PaymentNotification $n): string
    {
        $status = $checkout->payment_status;

        if ($n->result === PaymentNotification::PAID) {
            if ($status === PaymentStatus::Pending) {
                $checkout->forceFill([
                    'payment_status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                    'payment_method' => $n->method,
                    'payment_reference' => $n->reference,
                ])->save();
                $checkout->orders()->update(['status' => OrderStatus::Paid]);

                return self::MARKED_PAID;
            }

            if ($status === PaymentStatus::Expired) {
                // Paid after we released the stock. Shipping it might mean
                // selling stock that now belongs to someone else, so the
                // money goes straight back.
                $checkout->forceFill([
                    'payment_status' => PaymentStatus::NeedsRefund,
                    'paid_at' => now(),
                    'payment_method' => $n->method,
                    'payment_reference' => $n->reference,
                ])->save();

                $this->requestRefund->forCheckout($checkout, 'Pembayaran diterima setelah pesanan dibatalkan.');

                return self::NEEDS_REFUND;
            }

            return self::IGNORED; // duplicate of a payment we already have
        }

        if ($n->result === PaymentNotification::FAILED && $status === PaymentStatus::Pending) {
            $this->expire->cancelLocked($checkout);

            return self::CANCELLED;
        }

        return self::IGNORED;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function log(array $payload, ?Checkout $checkout, string $outcome, bool $signatureValid, string $source): void
    {
        DB::table('payment_notifications')->insert([
            'source' => $source,
            'checkout_id' => $checkout?->id,
            'order_id' => mb_substr((string) ($payload['order_id'] ?? ''), 0, 50),
            'transaction_status' => isset($payload['transaction_status']) ? mb_substr((string) $payload['transaction_status'], 0, 30) : null,
            'signature_valid' => $signatureValid,
            'outcome' => $outcome,
            'payload' => json_encode($payload),
            'created_at' => now(),
        ]);
    }
}
