<?php

namespace App\Payments;

use App\Models\Checkout;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Midtrans Snap: https://docs.midtrans.com/reference/backend-integration
 * The browser opens the payment popup with the token we create here.
 */
class Midtrans implements PaymentGateway
{
    public function __construct(
        private readonly ?string $serverKey,
        private readonly ?string $clientKey,
        private readonly bool $production,
    ) {}

    public function createPaymentToken(Checkout $checkout): string
    {
        if ($this->serverKey === null || $this->serverKey === '') {
            throw new PaymentUnavailable('MIDTRANS_SERVER_KEY is not set.');
        }

        $checkout->loadMissing(['orders.items', 'orders.store:id,name', 'user:id,name,email']);
        $items = $this->itemDetails($checkout);

        // Midtrans rejects a request whose lines don't add up to the total.
        // If they don't, it's OUR bug: stop before charging anything.
        $sum = array_sum(array_map(fn (array $i) => $i['price'] * $i['quantity'], $items));
        if ($sum !== $checkout->grand_total) {
            throw new PaymentUnavailable("Item total {$sum} != grand total {$checkout->grand_total} for {$checkout->code}.");
        }

        try {
            $response = Http::baseUrl($this->apiUrl())
                // Basic auth: server key as username, empty password.
                ->withBasicAuth($this->serverKey, '')
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->post('snap/v1/transactions', [
                    'transaction_details' => [
                        // Our public code: this is what appears in the
                        // Midtrans dashboard and comes back in webhooks.
                        'order_id' => $checkout->code,
                        'gross_amount' => $checkout->grand_total,
                    ],
                    'item_details' => $items,
                    'customer_details' => $this->customer($checkout),
                    // The payment screen closes when our stock hold ends.
                    'expiry' => [
                        'start_time' => now('Asia/Jakarta')->format('Y-m-d H:i:s O'),
                        'unit' => 'minute',
                        // Midtrans accepts 5 minutes at the least.
                        'duration' => max(5, (int) ceil(now()->diffInMinutes($checkout->expires_at))),
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new PaymentUnavailable('Midtrans unreachable: '.$e->getMessage(), previous: $e);
        }

        $token = $response->json('token');

        if ($response->failed() || ! is_string($token)) {
            // error_messages explain what Midtrans disliked; for the log.
            throw new PaymentUnavailable("Midtrans Snap failed: HTTP {$response->status()} ".json_encode($response->json('error_messages')));
        }

        return $token;
    }

    /**
     * https://docs.midtrans.com/docs/https-notification-webhooks
     */
    public function parseNotification(array $payload): ?PaymentNotification
    {
        if ($this->serverKey === null || $this->serverKey === '') {
            return null;
        }

        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature = (string) ($payload['signature_key'] ?? '');

        // Only Midtrans and we know the server key, so only Midtrans can
        // produce this hash. Anyone can POST "settlement" to our URL; they
        // just can't sign it.
        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey);

        // hash_equals: constant-time comparison. A normal === stops at the
        // first different character, and measuring that time difference
        // could leak the correct signature bit by bit (timing attack).
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return null;
        }

        $status = (string) ($payload['transaction_status'] ?? '');
        $fraud = $payload['fraud_status'] ?? null;

        $result = match (true) {
            $status === 'settlement' => PaymentNotification::PAID,
            // Cards: captured AND not flagged by fraud detection.
            $status === 'capture' && ($fraud === null || $fraud === 'accept') => PaymentNotification::PAID,
            in_array($status, ['deny', 'cancel', 'expire', 'failure'], true) => PaymentNotification::FAILED,
            $status === 'pending' || $status === 'capture' => PaymentNotification::WAITING,
            default => PaymentNotification::OTHER,
        };

        return new PaymentNotification(
            orderId: $orderId,
            result: $result,
            // "118000.00": whole Rupiah for IDR.
            amount: (int) round((float) $grossAmount),
            method: isset($payload['payment_type']) ? (string) $payload['payment_type'] : null,
            reference: isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            rawStatus: $status,
        );
    }

    /**
     * @return array{client_key: string|null, snap_js: string}
     */
    public function clientConfig(): array
    {
        return [
            'client_key' => $this->clientKey,
            'snap_js' => $this->production
                ? 'https://app.midtrans.com/snap/snap.js'
                : 'https://app.sandbox.midtrans.com/snap/snap.js',
        ];
    }

    private function apiUrl(): string
    {
        return $this->production ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }

    /**
     * Every product line, plus one shipping line per store, so the buyer
     * sees the same breakdown in the Midtrans popup as on our page.
     *
     * @return list<array{id: string, price: int, quantity: int, name: string}>
     */
    private function itemDetails(Checkout $checkout): array
    {
        $lines = [];

        foreach ($checkout->orders as $order) {
            foreach ($order->items as $item) {
                $lines[] = [
                    'id' => "v{$item->product_variant_id}",
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    // Midtrans limits names to 50 characters.
                    'name' => Str::limit(trim($item->product_name.' '.($item->variant_label ?? '')), 47),
                ];
            }

            $lines[] = [
                'id' => "ongkir-{$order->id}",
                'price' => $order->shipping_cost,
                'quantity' => 1,
                'name' => Str::limit('Ongkir '.$order->store->name, 47),
            ];
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function customer(Checkout $checkout): array
    {
        $order = $checkout->orders->first();

        return array_filter([
            'first_name' => Str::limit($checkout->user->name, 47),
            'email' => $checkout->user->email,
            'phone' => $order?->phone,
        ]);
    }
}
