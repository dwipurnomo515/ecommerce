<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Store;
use App\Models\User;
use App\Refunds\RefundGateway;
use App\Shipping\Destination;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Store $store;

    private ProductVariant $variant;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.rajaongkir.key' => 'test-key', 'services.midtrans.server_key' => 'SB-Mid-server-TEST']);
        Http::fake(['*/calculate/domestic-cost' => Http::response(['data' => [
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => '', 'cost' => 18_000, 'etd' => ''],
        ]])]);

        $this->buyer = User::factory()->create();
        Address::factory()->for($this->buyer)->primary()->create();
        $this->store = Store::factory()->active()->create();
        $this->store->setShippingOrigin(new Destination(1, 'ASAL', 'P', 'K', 'D', 'S', '10000'));
        $this->store->save();

        $this->variant = ProductVariant::factory()
            ->for(Product::factory()->active()->for($this->store))
            ->create(['price' => 50_000, 'stock' => 5]);
        $this->buyer->cartItems()->create(['product_variant_id' => $this->variant->id, 'quantity' => 2]);

        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'address_id' => $this->buyer->addresses()->value('id'),
            'shipping' => [$this->store->id => 'jne:REG'],
            'idempotency_key' => (string) Str::uuid(),
            'expected_total' => 118_000,
        ])->assertSessionHasNoErrors();

        $this->order = Order::query()->sole();
        $this->order->status = OrderStatus::Paid;
        $this->order->save();
        $this->order->checkout->forceFill(['payment_status' => PaymentStatus::Paid])->save();
    }

    private function cancel(string $reason = 'Stok ukuran L ternyata habis.', ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->store->user)
            ->post(route('seller.orders.cancel', $this->order), ['reason' => $reason]);
    }

    public function test_cancelling_a_paid_order_returns_stock_and_refunds_the_buyer(): void
    {
        $this->assertSame(3, $this->variant->fresh()?->stock);

        $this->cancel()->assertSessionHasNoErrors();

        $order = $this->order->fresh();
        $this->assertSame(OrderStatus::Cancelled, $order?->status);
        $this->assertSame('Stok ukuran L ternyata habis.', $order?->cancel_reason);
        $this->assertSame(5, $this->variant->fresh()?->stock);

        // Sync queue in tests: the refund job already ran.
        $refund = Refund::query()->sole();
        $this->assertSame([118_000, Refund::REFUNDED], [$refund->amount, $refund->status], 'Items + shipping.');
        $this->assertSame(sprintf('SIMREF-%08d', $refund->id), $refund->reference);
        $this->assertSame(0, (int) $this->store->fresh()?->balance, 'Escrow: the store was never paid.');
    }

    public function test_it_can_also_be_cancelled_while_processing_but_not_after_shipping(): void
    {
        $this->order->status = OrderStatus::Processing;
        $this->order->save();
        $this->cancel()->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Cancelled, $this->order->fresh()?->status);

        $this->order->forceFill(['status' => OrderStatus::Shipped])->save();
        Refund::query()->delete();
        $this->cancel();
        $this->assertSame(OrderStatus::Shipped, $this->order->fresh()?->status);
        $this->assertSame(0, Refund::query()->count());
    }

    public function test_a_second_cancel_never_refunds_twice(): void
    {
        $this->cancel();
        $this->cancel(); // already cancelled: refused by the state machine

        $this->assertSame(1, Refund::query()->count());
        $this->assertSame(5, $this->variant->fresh()?->stock, 'Stock returned once.');
    }

    public function test_the_reason_is_required_and_only_the_seller_may_cancel(): void
    {
        $this->cancel('habis')->assertSessionHasErrors('reason');
        $this->cancel(as: $this->buyer)->assertForbidden();
        $this->cancel(as: Store::factory()->active()->create()->user)->assertForbidden();

        $this->assertSame(OrderStatus::Paid, $this->order->fresh()?->status);
    }

    public function test_the_buyer_sees_why_and_the_refund_status(): void
    {
        $this->cancel();

        $this->actingAs($this->buyer)
            ->get(route('checkout.placed', $this->order->checkout->code))
            ->assertInertia(fn (Assert $p) => $p
                ->where('checkout.orders.0.cancel_reason', 'Stok ukuran L ternyata habis.')
                ->where('checkout.orders.0.refund.amount', 118_000)
                ->where('checkout.orders.0.refund.label', 'Dana dikembalikan'));
    }

    public function test_money_arriving_after_expiry_is_refunded_automatically(): void
    {
        $checkout = $this->order->checkout;
        $checkout->forceFill(['payment_status' => PaymentStatus::Expired])->save();

        $payload = ['order_id' => $checkout->code, 'status_code' => '200', 'gross_amount' => '118000.00',
            'transaction_status' => 'settlement', 'payment_type' => 'bank_transfer', 'transaction_id' => 'trx-late'];
        $payload['signature_key'] = hash('sha512', $checkout->code.'200118000.00SB-Mid-server-TEST');
        $this->postJson(route('webhooks.midtrans'), $payload)->assertJson(['outcome' => 'needs_refund']);
        $this->postJson(route('webhooks.midtrans'), $payload)->assertJson(['outcome' => 'ignored']); // duplicate

        $refund = Refund::query()->sole();
        $this->assertNull($refund->order_id, 'Whole checkout.');
        $this->assertSame([118_000, Refund::REFUNDED], [$refund->amount, $refund->status]);
    }

    public function test_the_refund_simulation_is_refused_in_production(): void
    {
        $this->app->forgetInstance(RefundGateway::class);
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        app(RefundGateway::class);
    }

    public function test_the_database_refuses_a_second_refund_for_the_same_order(): void
    {
        $this->cancel();

        $this->expectException(UniqueConstraintViolationException::class);
        $again = new Refund;
        $again->forceFill(['checkout_id' => $this->order->checkout_id, 'order_id' => $this->order->id,
            'amount' => 1, 'reason' => 'x', 'status' => Refund::PENDING])->save();
    }
}
