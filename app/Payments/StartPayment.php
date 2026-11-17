<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Checkout;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * "Bayar" clicked: returns the payment token for this checkout, creating it
 * on the first click and reusing it afterwards (Midtrans accepts each order
 * id only once).
 */
class StartPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * @throws ValidationException with a message for the buyer under "payment"
     */
    public function handle(Checkout $checkout): string
    {
        try {
            // Two tabs clicking "Bayar" at once would both ask Midtrans for a
            // token with the same order id. A cache lock (Redis) lets one go
            // first; the other waits, then finds the saved token.
            // Not a database row lock: we'd hold it while waiting for
            // Midtrans, blocking the expiry job for that checkout.
            return Cache::lock("payment-token:{$checkout->id}", 20)->block(12, function () use ($checkout): string {
                $checkout->refresh();

                if ($checkout->payment_status !== PaymentStatus::Pending || $checkout->expires_at->isPast()) {
                    $this->fail('Pesanan ini sudah tidak bisa dibayar.');
                }

                if ($checkout->payment_token !== null) {
                    return $checkout->payment_token;
                }

                try {
                    $token = $this->gateway->createPaymentToken($checkout);
                } catch (PaymentUnavailable $e) {
                    report($e);
                    $this->fail('Pembayaran sedang tidak tersedia. Coba lagi sebentar lagi.');
                }

                $checkout->payment_token = $token;
                $checkout->save();

                return $token;
            });
        } catch (LockTimeoutException) {
            $this->fail('Sedang memproses pembayaran dari tab lain. Coba lagi sebentar.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
