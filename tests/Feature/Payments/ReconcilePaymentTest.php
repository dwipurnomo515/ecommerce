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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReconcilePaymentTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_URL = 'https://api.sandbox.midtrans.com/v2/*/status';

    private User $buyer;

    private Checkout $checkout;

    private ProductVariant $variant;

    /** What the fake Midtrans status API answers. */
    private array $midtrans = ['status_code' => '404', 'status_message' => "Transaction doesn't exist."];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rajaongkir.key' => 'test-key',
            'services.midtrans.server_key' => 'SB-Mid-server-TEST',
            'services.midtrans.client_key' => 'SB-Mid-client-TEST',
        ]);
        Http::fake([
            '*/calculate/domestic-cost' => Http::response(['data' => [
                ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => '', 'cost' => 18_000, 'etd' => ''],
            ]]),
            self::STATUS_URL => fn () => Http::response($this->midtrans),
        ]);

        $this->buyer = User::factory()->create();
        Address::factory()->for($this->buyer)->primary()->create();
        $store = Store::factory()->active()->create();
        $store->setShippingOrigin(new Destination(1, 'ASAL', 'P', 'K', 'D', 'S', '10000'));
        $store->save();
        $this->variant = ProductVariant::factory()
            ->for(Product::factory()->active()->for($store))
            ->create(['price' => 50_000, 'stock' => 5]);
        $this->buyer->cartItems()->create(['product_variant_id' => $this->variant->id, 'quantity' => 2]);

        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'address_id' => $this->buyer->addresses()->value('id'),
            'shipping' => [$store->id => 'jne:REG'],
            'idempotency_key' => (string) Str::uuid(),
            'expected_total' => 118_000,
        ])->assertSessionHasNoErrors();

        $this->checkout = Checkout::query()->sole();
        // The buyer opened the payment popup (a token exists).
        $this->checkout->payment_token = 'snap-token';
        $this->checkout->save();
    }

    private function midtransSays(string $status, string $amount = '118000.00'): void
    {
        $this->midtrans = [
            'status_code' => $status === 'settlement' ? '200' : '201',
            'transaction_status' => $status,
            'gross_amount' => $amount,
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'trx-xyz',
            'order_id' => $this->checkout->code,
        ];
    }

    private function statusCalls(): int
    {
        return count(Http::recorded(fn (Request $r) => str_contains($r->url(), '/status')));
    }

    public function test_opening_the_order_page_picks_up_a_payment_whose_webhook_was_lost(): void
    {
        $this->midtransSays('settlement');

        $this->actingAs($this->buyer)->get(route('checkout.placed', $this->checkout->code))->assertOk();

        $checkout = $this->checkout->fresh();
        $this->assertSame(PaymentStatus::Paid, $checkout?->payment_status);
        $this->assertSame(OrderStatus::Paid, $checkout?->orders()->sole()->status);
        $this->assertDatabaseHas('payment_notifications', ['source' => 'status_check', 'outcome' => 'marked_paid']);

        Http::assertSent(fn (Request $r) => $r->url() === "https://api.sandbox.midtrans.com/v2/{$this->checkout->code}/status"
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-TEST:')));
    }

    public function test_reloading_the_page_asks_midtrans_at_most_every_30_seconds(): void
    {
        $page = fn () => $this->actingAs($this->buyer)->get(route('checkout.placed', $this->checkout->code));

        $page();
        $page();
        $page();
        $this->assertSame(1, $this->statusCalls());

        $this->travel(31)->seconds();
        $page();
        $this->assertSame(2, $this->statusCalls());
    }

    public function test_nothing_is_asked_before_the_payment_popup_was_opened(): void
    {
        $this->checkout->payment_token = null;
        $this->checkout->save();

        $this->actingAs($this->buyer)->get(route('checkout.placed', $this->checkout->code))->assertOk();

        $this->assertSame(0, $this->statusCalls());
    }

    public function test_expiry_does_not_cancel_an_order_that_was_actually_paid(): void
    {
        $this->midtransSays('settlement');
        $this->travel(2)->days();

        $this->artisan('orders:expire')->expectsOutput('0 checkout kedaluwarsa dibatalkan.');

        $this->assertSame(PaymentStatus::Paid, $this->checkout->fresh()?->payment_status);
        $this->assertSame(3, $this->variant->fresh()?->stock, 'Stock stays sold.');
    }

    public function test_expiry_still_cancels_when_midtrans_has_no_payment(): void
    {
        $this->travel(2)->days(); // Midtrans: "Transaction doesn't exist."

        $this->artisan('orders:expire')->expectsOutput('1 checkout kedaluwarsa dibatalkan.');

        $this->assertSame(PaymentStatus::Expired, $this->checkout->fresh()?->payment_status);
        $this->assertSame(5, $this->variant->fresh()?->stock);
    }

    public function test_the_sync_command_applies_the_same_rules_as_webhooks(): void
    {
        $this->midtransSays('settlement', amount: '1000.00'); // wrong amount

        $this->artisan('payments:sync')->expectsOutput('0 pembayaran diperbarui dari Midtrans.');

        $this->assertSame(PaymentStatus::Pending, $this->checkout->fresh()?->payment_status);
        $this->assertDatabaseHas('payment_notifications', ['source' => 'status_check', 'outcome' => 'amount_mismatch']);
    }

    public function test_a_status_check_and_a_late_webhook_do_not_double_apply(): void
    {
        $this->midtransSays('settlement');
        $this->artisan('payments:sync')->expectsOutput('1 pembayaran diperbarui dari Midtrans.');

        // The original webhook finally arrives.
        $payload = ['order_id' => $this->checkout->code, 'status_code' => '200', 'gross_amount' => '118000.00',
            'transaction_status' => 'settlement', 'payment_type' => 'bank_transfer', 'transaction_id' => 'trx-xyz'];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].'200'.'118000.00'.'SB-Mid-server-TEST');
        $this->postJson(route('webhooks.midtrans'), $payload)->assertJson(['outcome' => 'ignored']);

        $this->assertSame(PaymentStatus::Paid, $this->checkout->fresh()?->payment_status);
        $this->assertSame(2, DB::table('payment_notifications')->count());
    }

    public function test_a_midtrans_outage_does_not_break_the_order_page(): void
    {
        // An answer without a transaction status: treated as unavailable.
        $this->midtrans = ['status_code' => '500', 'status_message' => 'Internal error'];

        $this->actingAs($this->buyer)->get(route('checkout.placed', $this->checkout->code))->assertOk();

        $this->assertSame(PaymentStatus::Pending, $this->checkout->fresh()?->payment_status);
    }
}
