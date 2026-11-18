<?php

namespace App\Payments;

use App\Models\Checkout;

/**
 * What the shop needs from a payment provider. Like ShippingProvider: the
 * app depends on this, not on Midtrans, so tests (and a future provider)
 * plug in here.
 */
interface PaymentGateway
{
    /**
     * Opens a payment session for the checkout's grand total and returns
     * the token the browser needs to show the payment screen.
     *
     * @throws PaymentUnavailable
     */
    public function createPaymentToken(Checkout $checkout): string;

    /**
     * Checks that a webhook really comes from the provider and translates
     * it. Null = the signature is wrong: treat it as forged.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseNotification(array $payload): ?PaymentNotification;

    /**
     * Asks the provider for the payment's current state, for when a webhook
     * never arrived. Null = no payment was started for this order yet.
     *
     * @throws PaymentUnavailable
     */
    public function fetchStatus(string $orderId): ?PaymentNotification;

    /**
     * Public key/config the browser needs (never secrets).
     *
     * @return array{client_key: string|null, snap_js: string}
     */
    public function clientConfig(): array;
}
