<?php

namespace Tests\Feature\Shop;

use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Checkout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Payments\PaymentGateway;
use App\Shipping\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StartPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SNAP_URL = 'https://app.sandbox.midtrans.com/snap/v1/transactions';

    private const FAKE_SERVER_KEY = 'SB-Mid-server-TEST';

    private User $buyer;

    private Checkout $checkout;

    private bool $snapFails = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rajaongkir.key' => 'test-key',
            'services.midtrans.server_key' => self::FAKE_SERVER_KEY,
            'services.midtrans.client_key' => 'SB-Mid-client-TEST',
            'services.midtrans.production' => false,
        ]);
        Http::fake([
            '*/calculate/domestic-cost' => Http::response(['data' => [
                ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => '', 'cost' => 18_000, 'etd' => ''],
            ]]),
            // A closure so a test can switch the answer: a second
            // Http::fake() would be ADDED after this one and never reached.
            self::SNAP_URL => fn () => $this->snapFails
                ? Http::response(['error_messages' => ['gross_amount tidak sama']], 400)
                : Http::response(['token' => 'snap-token-123', 'redirect_url' => 'https://example.test'], 201),
        ]);

        $this->buyer = User::factory()->create(['name' => 'Budi']);
        Address::factory()->for($this->buyer)->primary()->create(['phone' => '081234567890']);

        $store = Store::factory()->active()->create(['name' => 'Toko Contoh']);
        $store->setShippingOrigin(new Destination(1, 'ASAL', 'P', 'K', 'D', 'S', '10000'));
        $store->save();

        $variant = ProductVariant::factory()
            ->for(Product::factory()->active()->for($store)->create(['name' => 'Kaos Polos']))
            ->create(['price' => 50_000, 'stock' => 5]);
        $this->buyer->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);

        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'address_id' => $this->buyer->addresses()->value('id'),
            'shipping' => [$store->id => 'jne:REG'],
            'idempotency_key' => (string) Str::uuid(),
            'expected_total' => 118_000,
        ])->assertSessionHasNoErrors();

        $this->checkout = Checkout::query()->sole();
    }

    private function pay(?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->buyer)
            ->from(route('checkout.placed', $this->checkout->code))
            ->post(route('checkout.pay', $this->checkout->code));
    }

    private function snapCalls(): int
    {
        return count(Http::recorded(fn (Request $r) => $r->url() === self::SNAP_URL));
    }

    public function test_the_first_click_creates_a_snap_token_for_the_exact_total(): void
    {
        $this->pay()
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('checkout.placed', $this->checkout->code))
            ->assertInertiaFlash('payment.token', 'snap-token-123');

        Http::assertSent(function (Request $r) {
            if ($r->url() !== self::SNAP_URL) {
                return false;
            }

            $sum = collect($r['item_details'])->sum(fn (array $i) => $i['price'] * $i['quantity']);

            return $r->hasHeader('Authorization', 'Basic '.base64_encode(self::FAKE_SERVER_KEY.':'))
                && $r['transaction_details'] === ['order_id' => $this->checkout->code, 'gross_amount' => 118_000]
                && $sum === 118_000
                && collect($r['item_details'])->pluck('name')->contains('Ongkir Toko Contoh')
                && $r['customer_details']['phone'] === '081234567890'
                && $r['expiry']['unit'] === 'minute'
                && $r['expiry']['duration'] === 24 * 60
                // Back to the order page (which reconciles) after app/bank redirects.
                && $r['callbacks']['finish'] === route('checkout.placed', $this->checkout->code);
        });

        $this->assertSame('snap-token-123', $this->checkout->fresh()?->payment_token);
    }

    public function test_each_call_is_logged_in_one_line_without_secrets_or_buyer_data(): void
    {
        /** @var list<MessageLogged> $logged */
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
            $logged[] = $e;
        });

        $this->snapFails = true;
        $this->pay();
        $this->snapFails = false;
        $this->pay();

        $lines = collect($logged)->filter(fn (MessageLogged $e) => $e->message === 'Midtrans snap')->values();
        $this->assertCount(2, $lines);
        $this->assertSame(['gross_amount tidak sama'], $lines[0]->context['error_messages'], 'Why Midtrans refused.');
        $this->assertSame(['order_id' => $this->checkout->code, 'http' => 201], Arr::except($lines[1]->context, 'ms'));
        $this->assertIsInt($lines[1]->context['ms']);

        $everything = json_encode(array_map(fn (MessageLogged $e) => [$e->message, $e->context], $logged));
        foreach ([self::FAKE_SERVER_KEY, $this->buyer->email, '081234567890'] as $private) {
            $this->assertStringNotContainsString($private, (string) $everything);
        }
    }

    public function test_later_clicks_reuse_the_token(): void
    {
        $this->pay();
        $this->pay()->assertInertiaFlash('payment.token', 'snap-token-123');

        $this->assertSame(1, $this->snapCalls(), 'Midtrans accepts an order id only once.');
    }

    public function test_only_the_buyer_can_pay(): void
    {
        $this->pay(User::factory()->create())->assertNotFound();

        $this->assertSame(0, $this->snapCalls());
    }

    public function test_an_expired_or_paid_checkout_cannot_be_paid(): void
    {
        $this->checkout->payment_status = PaymentStatus::Expired;
        $this->checkout->save();

        $this->pay()->assertSessionHasErrors(['payment' => 'Pesanan ini sudah tidak bisa dibayar.']);

        $this->checkout->payment_status = PaymentStatus::Pending;
        $this->checkout->save();
        $this->travel(25)->hours();

        $this->pay()->assertSessionHasErrors('payment');
        $this->assertSame(0, $this->snapCalls());
    }

    public function test_a_midtrans_failure_is_explained_and_nothing_is_saved(): void
    {
        $this->snapFails = true;

        $this->pay()->assertSessionHasErrors(['payment' => 'Pembayaran sedang tidak tersedia. Coba lagi sebentar lagi.']);

        $this->assertNull($this->checkout->fresh()?->payment_token);
    }

    public function test_the_page_gets_the_public_client_key_but_never_the_server_key(): void
    {
        $response = $this->actingAs($this->buyer)->get(route('checkout.placed', $this->checkout->code));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('payment.client_key', 'SB-Mid-client-TEST')
            ->where('payment.snap_js', 'https://app.sandbox.midtrans.com/snap/snap.js'));
        $this->assertStringNotContainsString(self::FAKE_SERVER_KEY, (string) $response->getContent());
    }

    public function test_without_a_server_key_nothing_is_sent(): void
    {
        config(['services.midtrans.server_key' => null]);
        $this->app->forgetInstance(PaymentGateway::class);

        $this->pay()->assertSessionHasErrors('payment');
        $this->assertSame(0, $this->snapCalls());
    }
}
