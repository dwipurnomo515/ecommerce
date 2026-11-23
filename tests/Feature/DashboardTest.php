<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Review;
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
    private function placeOrder(User $buyer, string|Store $store = 'Toko Kaos'): Order
    {
        // One primary address per user (unique), reused for every order.
        $address = $buyer->addresses()->first() ?? Address::factory()->for($buyer)->primary()->create();
        if (is_string($store)) {
            $store = Store::factory()->active()->create(['name' => $store]);
            $store->setShippingOrigin(new Destination(1, 'ASAL', 'P', 'K', 'D', 'S', '10000'));
            $store->save();
        }

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
                ->where('buyer.active', [])
                // No store, no seller section.
                ->where('seller', null));
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

    public function test_the_seller_sees_what_needs_doing(): void
    {
        $store = Store::factory()->active()->create(['name' => 'Toko Kaos']);
        $store->setShippingOrigin(new Destination(1, 'ASAL', 'P', 'K', 'D', 'S', '10000'));
        $store->forceFill(['balance' => 75_000])->save();

        // Each order buys 1 of a variant with stock 5, leaving 4: low.
        $toProcess = $this->placeOrder($this->buyer, $store);
        $toShip = $this->placeOrder($this->buyer, $store);
        $done = $this->placeOrder($this->buyer, $store);
        $this->placeOrder($this->buyer, $store); // unpaid: not the seller's business yet
        $this->moveTo($toProcess, OrderStatus::Paid);
        $this->moveTo($toShip, OrderStatus::Processing);
        $this->moveTo($done, OrderStatus::Completed);

        // Sold out and plenty in stock; sold out comes first.
        $kaos = Product::factory()->active()->for($store)->create(['name' => 'Kaos Polos']);
        ProductVariant::factory()->for($kaos)->create(['stock' => 0, 'options' => ['Ukuran' => 'XL']]);
        ProductVariant::factory()->for($kaos)->create(['stock' => 40]);
        // Not on sale, so not counted: a draft, a removed variant, another store.
        ProductVariant::factory()->for(Product::factory()->for($store))->create(['stock' => 0]);
        ProductVariant::factory()->for($kaos)->create(['stock' => 0, 'is_active' => false]);
        ProductVariant::factory()->for(Product::factory()->active())->create(['stock' => 0]);

        // Unanswered counts; replied and hidden ones don't.
        $this->review($done, reply: null);
        $this->review($toShip, reply: 'Terima kasih!');
        $this->review($toProcess, reply: null, hidden: true);

        $this->actingAs($store->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('seller.store', ['name' => 'Toko Kaos', 'status' => 'active', 'status_label' => 'Aktif'])
                ->where('seller.counts', ['to_process' => 1, 'to_ship' => 1, 'unanswered_reviews' => 1, 'low_stock' => 5])
                ->where('seller.balance', [
                    'available' => 75_000,
                    'held' => $toProcess->fresh()?->sellerEarning() + $toShip->fresh()?->sellerEarning(),
                    'can_withdraw' => true,
                ])
                ->has('seller.low_stock', 5)
                ->where('seller.low_stock.0.product', 'Kaos Polos')
                ->where('seller.low_stock.0.variant', 'XL')
                ->where('seller.low_stock.0.stock', 0)
                ->where('seller.low_stock.1.stock', 4));
    }

    public function test_a_store_awaiting_approval_says_so(): void
    {
        $store = Store::factory()->for($this->buyer)->create();

        $this->actingAs($this->buyer)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('seller.store.status', 'pending')
                ->where('seller.counts', ['to_process' => 0, 'to_ship' => 0, 'unanswered_reviews' => 0, 'low_stock' => 0])
                ->where('seller.balance', ['available' => 0, 'held' => 0, 'can_withdraw' => false])
                ->where('seller.low_stock', []));

        $this->assertSame($store->id, $this->buyer->fresh()?->store?->id);
    }

    public function test_the_admin_sees_what_needs_a_decision(): void
    {
        $admin = User::factory()->admin()->create();
        Store::factory()->create(['name' => 'Toko Lama', 'created_at' => now()->subDays(3)]);
        Store::factory()->create(['name' => 'Toko Baru']);
        Store::factory()->active()->create(); // already decided

        // Low and recent counts; high, hidden or older than a week doesn't.
        $done = $this->placeOrder($this->buyer);
        $this->moveTo($done, OrderStatus::Completed);
        $this->review($done, reply: null, rating: 1);
        $this->review($this->placeOrder($this->buyer), reply: null, rating: 5);
        $this->review($this->placeOrder($this->buyer), reply: null, rating: 2, hidden: true);
        $this->travel(-8)->days();
        $this->review($this->placeOrder($this->buyer), reply: null, rating: 1);
        $this->travelBack();

        // Payouts: stuck for 2 hours counts, a fresh pending one doesn't.
        // (One pending payout per store at most, so two stores.)
        $payout = fn (int $storeId, int $minutesAgo) => (new Payout)->forceFill([
            'store_id' => $storeId, 'amount' => 60_000, 'bank' => 'bca',
            'account_number' => '1234567890', 'account_name' => 'Budi', 'status' => Payout::PENDING,
            'created_at' => now()->subMinutes($minutesAgo),
        ])->save();
        $payout($done->store_id, 120);
        $payout(Store::factory()->active()->create()->id, 5);

        // A failed refund: the buyer is still owed.
        (new Refund)->forceFill([
            'checkout_id' => $done->checkout_id, 'amount' => 68_000, 'reason' => 'Uji',
            'status' => Refund::FAILED, 'failure_reason' => 'Bank menolak',
        ])->save();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('admin.counts', ['pending_stores' => 2, 'low_reviews' => 1, 'payouts' => 1, 'refunds' => 1])
                // Oldest first.
                ->where('admin.pending_stores.0.name', 'Toko Lama')
                ->where('admin.pending_stores.1.name', 'Toko Baru'));
    }

    public function test_only_admins_get_the_admin_section(): void
    {
        Store::factory()->create(); // something an admin would see

        $this->actingAs($this->buyer)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('admin', null));
    }

    /**
     * A review on the order's first item, as WriteReview would store it
     * (that path itself is covered in Shop\ReviewTest).
     */
    private function review(Order $order, ?string $reply, bool $hidden = false, int $rating = 5): void
    {
        $item = $order->items()->firstOrFail();

        (new Review)->forceFill([
            'order_item_id' => $item->id, 'product_id' => $item->product_id, 'store_id' => $order->store_id,
            'user_id' => User::factory()->create()->id, 'rating' => $rating, 'body' => 'Bagus.',
            'seller_reply' => $reply,
            'hidden_at' => $hidden ? now() : null, 'hidden_reason' => $hidden ? 'Spam' : null,
        ])->save();
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
