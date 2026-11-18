<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Shipping\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderFulfilmentTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Store $store;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.rajaongkir.key' => 'test-key']);
        Http::fake(['*/calculate/domestic-cost' => Http::response(['data' => [
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => '', 'cost' => 18_000, 'etd' => ''],
        ]])]);

        $this->buyer = User::factory()->create();
        Address::factory()->for($this->buyer)->primary()->create(['recipient_name' => 'Budi']);
        $this->store = Store::factory()->active()->create();
        $this->store->setShippingOrigin(new Destination(1, 'ASAL', 'P', 'K', 'D', 'S', '10000'));
        $this->store->save();

        $variant = ProductVariant::factory()
            ->for(Product::factory()->active()->for($this->store)->create(['name' => 'Kaos Polos']))
            ->create(['price' => 50_000, 'stock' => 5, 'sku' => 'KAOS-1']);
        $this->buyer->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);

        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'address_id' => $this->buyer->addresses()->value('id'),
            'shipping' => [$this->store->id => 'jne:REG'],
            'idempotency_key' => (string) Str::uuid(),
            'expected_total' => 118_000,
        ])->assertSessionHasNoErrors();

        $this->order = Order::query()->sole();
    }

    private function markPaid(): void
    {
        $this->order->status = OrderStatus::Paid;
        $this->order->save();
    }

    private function seller(): User
    {
        return $this->store->user;
    }

    public function test_the_seller_sees_paid_orders_but_not_unpaid_ones(): void
    {
        $page = fn () => $this->actingAs($this->seller())->get(route('seller.orders.index'));

        $page()->assertInertia(fn (Assert $p) => $p
            ->component('seller/orders/index')
            ->where('status', 'paid')
            ->has('orders.data', 0));

        $this->markPaid();

        $page()->assertInertia(fn (Assert $p) => $p
            ->has('orders.data', 1)
            ->where('orders.data.0.ship_to.recipient_name', 'Budi')
            ->where('orders.data.0.items.0.sku', 'KAOS-1')
            ->where('tabs.0.label', 'Perlu diproses')
            ->where('tabs.0.count', 1));
    }

    public function test_the_full_path_from_paid_to_completed(): void
    {
        $this->markPaid();
        $this->freezeSecond();

        $this->actingAs($this->seller())->post(route('seller.orders.process', $this->order))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Processing, $this->order->fresh()?->status);

        $this->actingAs($this->seller())->post(route('seller.orders.ship', $this->order), ['tracking_number' => 'jp1234567890'])
            ->assertSessionHasNoErrors();
        $order = $this->order->fresh();
        $this->assertSame(OrderStatus::Shipped, $order?->status);
        $this->assertSame('JP1234567890', $order?->tracking_number, 'Uppercased.');
        $this->assertTrue(now()->equalTo($order?->shipped_at));

        $this->actingAs($this->buyer)->post(route('orders.confirm', $this->order))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Completed, $this->order->fresh()?->status);
    }

    public function test_steps_cannot_be_skipped_or_repeated(): void
    {
        $this->markPaid();

        // Ship before processing: refused, status unchanged.
        $this->actingAs($this->seller())->post(route('seller.orders.ship', $this->order), ['tracking_number' => 'JP1234567890']);
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()?->status);

        // The buyer can't "receive" something that was never shipped.
        $this->actingAs($this->buyer)->post(route('orders.confirm', $this->order));
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()?->status);

        // An unpaid order can't be processed.
        $this->order->status = OrderStatus::AwaitingPayment;
        $this->order->save();
        $this->actingAs($this->seller())->post(route('seller.orders.process', $this->order));
        $this->assertSame(OrderStatus::AwaitingPayment, $this->order->fresh()?->status);
    }

    public function test_only_the_right_people_can_act(): void
    {
        $this->markPaid();
        $otherSeller = Store::factory()->active()->create()->user;

        $this->actingAs($otherSeller)->post(route('seller.orders.process', $this->order))->assertForbidden();
        $this->actingAs($this->buyer)->post(route('seller.orders.process', $this->order))->assertForbidden();

        $this->order->forceFill(['status' => OrderStatus::Shipped, 'shipped_at' => now()])->save();
        $this->actingAs($this->seller())->post(route('orders.confirm', $this->order))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('orders.confirm', $this->order))->assertForbidden();

        $this->assertSame(OrderStatus::Shipped, $this->order->fresh()?->status);
    }

    public function test_a_tracking_number_is_required_and_checked(): void
    {
        $this->order->status = OrderStatus::Processing;
        $this->order->save();

        foreach (['', '12345', 'JP 123 456', '<script>'] as $bad) {
            $this->actingAs($this->seller())
                ->post(route('seller.orders.ship', $this->order), ['tracking_number' => $bad])
                ->assertSessionHasErrors('tracking_number');
        }

        $this->assertSame(OrderStatus::Processing, $this->order->fresh()?->status);
    }

    public function test_shipped_orders_complete_by_themselves_after_seven_days(): void
    {
        $this->order->forceFill(['status' => OrderStatus::Shipped, 'shipped_at' => now()])->save();

        $this->travel(Order::AUTO_COMPLETE_DAYS)->days();
        $this->travel(-1)->minutes();
        $this->artisan('orders:auto-complete')->expectsOutput('0 pesanan diselesaikan otomatis.');

        $this->travel(2)->minutes();
        $this->artisan('orders:auto-complete')->expectsOutput('1 pesanan diselesaikan otomatis.');
        $this->assertSame(OrderStatus::Completed, $this->order->fresh()?->status);
    }

    public function test_the_buyer_sees_the_tracking_number(): void
    {
        $this->order->forceFill(['status' => OrderStatus::Shipped, 'shipped_at' => now(), 'tracking_number' => 'JP1234567890'])->save();
        $code = Checkout::query()->sole()->code;

        $this->actingAs($this->buyer)
            ->get(route('checkout.placed', $code))
            ->assertInertia(fn (Assert $p) => $p
                ->where('checkout.orders.0.status', 'shipped')
                ->where('checkout.orders.0.status_label', 'Dikirim')
                ->where('checkout.orders.0.tracking_number', 'JP1234567890'));
    }
}
