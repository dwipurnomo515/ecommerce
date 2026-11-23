<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.rajaongkir.key' => 'test-key']);
        Http::fake(['*/calculate/domestic-cost' => Http::response(['data' => [
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => '', 'cost' => 18_000, 'etd' => ''],
        ]])]);

        $this->buyer = User::factory()->create();
    }

    /**
     * Places a real order (one checkout, one store) for $buyer.
     */
    private function placeOrder(User $buyer, string $storeName = 'Toko Kaos'): Order
    {
        // One primary address per user (unique), reused for every order.
        $address = $buyer->addresses()->first() ?? Address::factory()->for($buyer)->primary()->create();
        $store = Store::factory()->active()->create(['name' => $storeName]);
        $store->setShippingOrigin(new Destination(1, 'ASAL', 'P', 'K', 'D', 'S', '10000'));
        $store->save();

        $variant = ProductVariant::factory()
            ->for(Product::factory()->active()->for($store))
            ->create(['price' => 50_000, 'stock' => 5]);
        $buyer->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'address_id' => $address->id,
            'shipping' => [$store->id => 'jne:REG'],
            'idempotency_key' => (string) Str::uuid(),
            'expected_total' => 68_000,
        ])->assertSessionHasNoErrors();

        return Order::query()->where('user_id', $buyer->id)->latest('id')->firstOrFail();
    }

    /**
     * As if it was paid and the seller moved it on: an order only leaves
     * "awaiting payment" once its checkout is paid.
     */
    private function moveTo(Order $order, OrderStatus $status, ?string $tracking = null): void
    {
        Checkout::query()->whereKey($order->checkout_id)->update(['payment_status' => PaymentStatus::Paid]);
        $order->forceFill(['status' => $status, 'tracking_number' => $tracking])->save();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_new_buyer_sees_an_empty_summary(): void
    {
        $this->actingAs($this->buyer)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('buyer.counts', ['unpaid' => 0, 'in_progress' => 0, 'shipped' => 0])
                ->where('buyer.unpaid', [])
                ->where('buyer.active', []));
    }

    public function test_an_unpaid_checkout_is_listed_until_its_deadline(): void
    {
        $order = $this->placeOrder($this->buyer);
        $code = Checkout::query()->whereKey($order->checkout_id)->value('code');

        $this->actingAs($this->buyer)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('buyer.counts.unpaid', 1)
                ->where('buyer.unpaid.0.code', $code)
                ->where('buyer.unpaid.0.grand_total', 68_000)
                ->where('buyer.active', []));

        // Past the 24 h deadline: no longer payable, so not "to pay".
        $this->travel(25)->hours();

        $this->actingAs($this->buyer)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('buyer.counts.unpaid', 0));
    }

    public function test_paid_orders_are_followed_until_they_are_done(): void
    {
        $packing = $this->placeOrder($this->buyer, 'Toko Kaos');
        $shipped = $this->placeOrder($this->buyer, 'Toko Mug');
        $done = $this->placeOrder($this->buyer, 'Toko Topi');
        $this->moveTo($packing, OrderStatus::Processing);
        $this->moveTo($shipped, OrderStatus::Shipped, 'JP123');
        $this->moveTo($done, OrderStatus::Completed);

        $this->actingAs($this->buyer)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('buyer.counts', ['unpaid' => 0, 'in_progress' => 1, 'shipped' => 1])
                ->has('buyer.active', 2)
                ->where('buyer.active', fn ($rows) => collect($rows)->pluck('store')->sort()->values()->all() === ['Toko Kaos', 'Toko Mug'])
                ->where('buyer.active', fn ($rows) => collect($rows)->firstWhere('store', 'Toko Mug')['tracking_number'] === 'JP123'));
    }

    public function test_nobody_sees_someone_elses_orders(): void
    {
        $other = User::factory()->create();
        $this->moveTo($this->placeOrder($other), OrderStatus::Shipped, 'JP999');

        $this->actingAs($this->buyer)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('buyer.counts', ['unpaid' => 0, 'in_progress' => 0, 'shipped' => 0])
                ->where('buyer.active', []));
    }
}
