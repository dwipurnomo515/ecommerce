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
     * Public key/config the browser needs (never secrets).
     *
     * @return array{client_key: string|null, snap_js: string}
     */
    public function clientConfig(): array;
}
