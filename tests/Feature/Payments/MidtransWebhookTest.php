<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Checkout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Shipping\Destination;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-TEST';

    private Checkout $checkout;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rajaongkir.key' => 'test-key',
            'services.midtrans.server_key' => self::SERVER_KEY,
        ]);
        Http::fake(['*/calculate/domestic-cost' => Http::response(['data' => [
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => '', 'cost' => 18_000, 'etd' => ''],
        ]])]);

        $buyer = User::factory()->create();
        Address::factory()->for($buyer)->primary()->create();
        $store = Store::factory()->active()->create();
        $store->setShippingOrigin(new Destination(1, 'ASAL', 'P', 'K', 'D', 'S', '10000'));
        $store->save();

        $this->variant = ProductVariant::factory()
            ->for(Product::factory()->active()->for($store))
            ->create(['price' => 50_000, 'stock' => 5]);
        $buyer->cartItems()->create(['product_variant_id' => $this->variant->id, 'quantity' => 2]);

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'address_id' => $buyer->addresses()->value('id'),
            'shipping' => [$store->id => 'jne:REG'],
            'idempotency_key' => (string) Str::uuid(),
            'expected_total' => 118_000,
        ])->assertSessionHasNoErrors();

        $this->checkout = Checkout::query()->sole();
        auth()->logout(); // webhooks come from Midtrans, not from a user
    }

    /**
     * A notification as Midtrans sends it (docs: https-notification-webhooks),
     * signed with the server key unless $signature is given.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function notify(string $status, array $overrides = [], ?string $signature = null): TestResponse
    {
        $payload = array_replace([
            'transaction_time' => '2026-11-17 10:00:00',
            'transaction_status' => $status,
            'transaction_id' => 'trx-123',
            'status_code' => $status === 'settlement' || $status === 'capture' ? '200' : '201',
            'payment_type' => 'bank_transfer',
            'order_id' => $this->checkout->code,
            'gross_amount' => '118000.00',
            'currency' => 'IDR',
        ], $overrides);

        $payload['signature_key'] = $signature ?? hash('sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].self::SERVER_KEY);

        return $this->postJson(route('webhooks.midtrans'), $payload);
    }

    private function paymentStatus(): PaymentStatus
    {
        return $this->checkout->fresh()?->payment_status ?? throw new \LogicException;
    }

    public function test_settlement_marks_the_checkout_and_its_orders_paid(): void
    {
        $this->freezeSecond();

        $this->notify('settlement')->assertOk()->assertJson(['outcome' => 'marked_paid']);

        $checkout = $this->checkout->fresh();
        $this->assertSame(PaymentStatus::Paid, $checkout?->payment_status);
        $this->assertTrue(now()->equalTo($checkout?->paid_at));
        $this->assertSame('bank_transfer', $checkout?->payment_method);
        $this->assertSame('trx-123', $checkout?->payment_reference);
        $this->assertSame(OrderStatus::Paid, $checkout?->orders()->sole()->status);
    }

    public function test_a_forged_notification_is_rejected_and_logged(): void
    {
        $this->notify('settlement', signature: str_repeat('a', 128))
            ->assertForbidden()
            ->assertJson(['outcome' => 'bad_signature']);

        $this->assertSame(PaymentStatus::Pending, $this->paymentStatus());
        $this->assertDatabaseHas('payment_notifications', ['order_id' => $this->checkout->code, 'signature_valid' => false]);
    }

    public function test_a_notification_signed_for_another_amount_is_rejected(): void
    {
        // Signed for 118000.00, but the body says 1000.00: the hash covers
        // gross_amount, so changing it breaks the signature.
        $valid = hash('sha512', $this->checkout->code.'200'.'118000.00'.self::SERVER_KEY);

        $this->notify('settlement', ['gross_amount' => '1000.00'], signature: $valid)->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $this->paymentStatus());
    }

    public function test_a_correctly_signed_but_wrong_amount_is_never_marked_paid(): void
    {
        $this->notify('settlement', ['gross_amount' => '1000.00'])
            ->assertOk()
            ->assertJson(['outcome' => 'amount_mismatch']);

        $this->assertSame(PaymentStatus::Pending, $this->paymentStatus());
    }

    public function test_duplicates_and_out_of_order_notifications_are_harmless(): void
    {
        $this->notify('settlement')->assertJson(['outcome' => 'marked_paid']);
        $this->notify('settlement')->assertJson(['outcome' => 'ignored']);  // duplicate
        $this->notify('pending')->assertJson(['outcome' => 'ignored']);     // late "pending"
        $this->notify('expire')->assertJson(['outcome' => 'ignored']);      // stale failure

        $this->assertSame(PaymentStatus::Paid, $this->paymentStatus());
        $this->assertSame(3, $this->variant->fresh()?->stock, 'Stock stays sold.');
        $this->assertSame(4, DB::table('payment_notifications')->count(), 'Every notification is logged.');
    }

    public function test_a_failed_payment_cancels_and_returns_the_stock(): void
    {
        $this->notify('deny')->assertJson(['outcome' => 'cancelled']);

        $this->assertSame(PaymentStatus::Expired, $this->paymentStatus());
        $this->assertSame(OrderStatus::Cancelled, $this->checkout->orders()->sole()->status);
        $this->assertSame(5, $this->variant->fresh()?->stock);

        $this->notify('cancel')->assertJson(['outcome' => 'ignored']); // no double return
        $this->assertSame(5, $this->variant->fresh()?->stock);
    }

    public function test_money_arriving_after_cancellation_is_flagged_for_refund(): void
    {
        $this->travel(2)->days();
        $this->artisan('orders:expire');
        $this->assertSame(5, $this->variant->fresh()?->stock);

        $this->notify('settlement')->assertJson(['outcome' => 'needs_refund']);

        $this->assertSame(PaymentStatus::NeedsRefund, $this->paymentStatus());
        $this->assertSame(OrderStatus::Cancelled, $this->checkout->orders()->sole()->status, 'Not shipped.');
        $this->assertSame(5, $this->variant->fresh()?->stock, 'Released stock is not taken back.');
    }

    public function test_card_capture_counts_only_when_fraud_check_accepts(): void
    {
        $this->notify('capture', ['payment_type' => 'credit_card', 'fraud_status' => 'challenge'])
            ->assertJson(['outcome' => 'ignored']);
        $this->assertSame(PaymentStatus::Pending, $this->paymentStatus());

        $this->notify('capture', ['payment_type' => 'credit_card', 'fraud_status' => 'accept'])
            ->assertJson(['outcome' => 'marked_paid']);
    }

    public function test_unknown_orders_are_acknowledged_so_midtrans_stops_retrying(): void
    {
        $this->notify('settlement', ['order_id' => 'ZTG-TIDAKADA'])
            ->assertOk()
            ->assertJson(['outcome' => 'unknown_order']);
    }

    public function test_the_webhook_needs_no_csrf_token_or_login(): void
    {
        // postJson in tests skips CSRF anyway; prove the route itself is
        // excluded so the real Midtrans request isn't refused with 419.
        $this->assertTrue(collect(app(ValidateCsrfToken::class)->getExcludedPaths())
            ->contains('webhooks/*'));
    }
}
