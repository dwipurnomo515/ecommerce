<?php

namespace Tests\Feature\Shop;

use App\Checkout\ExpireCheckout;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Checkout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Shipping\Destination;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpireCheckoutsTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private ProductVariant $kaos;

    private ProductVariant $topi;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.rajaongkir.key' => 'test-key']);
        Http::fake(['*/calculate/domestic-cost' => Http::response(['data' => [
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => '', 'cost' => 10_000, 'etd' => ''],
        ]])]);

        $this->buyer = User::factory()->create();
        Address::factory()->for($this->buyer)->primary()->create();

        $store = Store::factory()->active()->create();
        $store->setShippingOrigin(new Destination(1, 'ASAL', 'P', 'K', 'D', 'S', '10000'));
        $store->save();

        $product = Product::factory()->active()->for($store)->create();
        $this->kaos = ProductVariant::factory()->for($product)->create(['price' => 50_000, 'stock' => 10, 'options' => ['Ukuran' => 'M']]);
        $this->topi = ProductVariant::factory()->for($product)->create(['price' => 20_000, 'stock' => 4, 'options' => ['Ukuran' => 'L']]);
    }

    /** Buys 3 kaos + 1 topi (stock 10 → 7 and 4 → 3) and returns the checkout. */
    private function placeOrder(): Checkout
    {
        $this->buyer->cartItems()->create(['product_variant_id' => $this->kaos->id, 'quantity' => 3]);
        $this->buyer->cartItems()->create(['product_variant_id' => $this->topi->id, 'quantity' => 1]);

        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'address_id' => $this->buyer->addresses()->value('id'),
            'shipping' => [$this->kaos->product->store_id => 'jne:REG'],
            'idempotency_key' => (string) Str::uuid(),
            'expected_total' => 180_000,
        ])->assertSessionHasNoErrors();

        return Checkout::query()->latest('id')->firstOrFail();
    }

    private function stock(): array
    {
        return [$this->kaos->fresh()?->stock, $this->topi->fresh()?->stock];
    }

    public function test_an_overdue_checkout_is_cancelled_and_its_stock_returned(): void
    {
        $checkout = $this->placeOrder();
        $this->assertSame([7, 3], $this->stock());

        $this->travel(Checkout::PAYMENT_WINDOW_HOURS)->hours();
        $this->travel(1)->minutes();
        $this->artisan('orders:expire')->expectsOutput('1 checkout kedaluwarsa dibatalkan.')->assertSuccessful();

        $checkout->refresh();
        $this->assertSame(PaymentStatus::Expired, $checkout->payment_status);
        $this->assertSame(OrderStatus::Cancelled, $checkout->orders()->sole()->status);
        $this->assertSame([10, 4], $this->stock());
    }

    public function test_nothing_happens_before_the_deadline(): void
    {
        $checkout = $this->placeOrder();

        $this->travel(Checkout::PAYMENT_WINDOW_HOURS - 1)->hours();
        $this->artisan('orders:expire')->expectsOutput('0 checkout kedaluwarsa dibatalkan.');

        $this->assertSame(PaymentStatus::Pending, $checkout->fresh()?->payment_status);
        $this->assertSame([7, 3], $this->stock());
    }

    public function test_expiring_twice_returns_the_stock_only_once(): void
    {
        $checkout = $this->placeOrder();
        $this->travel(2)->days();

        $this->assertTrue(app(ExpireCheckout::class)->handle($checkout->id));
        $this->assertFalse(app(ExpireCheckout::class)->handle($checkout->id));
        $this->artisan('orders:expire');

        $this->assertSame([10, 4], $this->stock());
    }

    public function test_a_paid_checkout_is_never_expired(): void
    {
        $checkout = $this->placeOrder();
        $checkout->payment_status = PaymentStatus::Paid;
        $checkout->save();

        $this->travel(2)->days();
        $this->artisan('orders:expire');

        $this->assertSame(PaymentStatus::Paid, $checkout->fresh()?->payment_status);
        $this->assertSame([7, 3], $this->stock());
    }

    public function test_the_command_runs_every_five_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $e) => str_contains((string) $e->command, 'orders:expire'));

        $this->assertNotNull($event, 'orders:expire must be scheduled.');
        $this->assertSame('*/5 * * * *', $event->expression);
    }

    public function test_the_buyer_sees_their_orders_and_only_theirs(): void
    {
        $checkout = $this->placeOrder();
        $other = User::factory()->create();

        $this->actingAs($this->buyer)
            ->get(route('orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/orders')
                ->has('checkouts.data', 1)
                ->where('checkouts.data.0.code', $checkout->code)
                ->where('checkouts.data.0.stores.0.items_count', 2));

        $this->actingAs($other)
            ->get(route('orders.index'))
            ->assertInertia(fn (Assert $page) => $page->has('checkouts.data', 0));
    }
}
