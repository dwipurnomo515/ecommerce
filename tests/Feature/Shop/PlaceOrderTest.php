<?php

namespace Tests\Feature\Shop;

use App\Checkout\CheckoutPreview;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Shipping\Destination;
use App\Shipping\ShippingProvider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Address $address;

    private Store $store;

    private ProductVariant $kaos;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.rajaongkir.key' => 'test-key']);
        Http::fake(['*/calculate/domestic-cost' => Http::response(['data' => [
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 18_000, 'etd' => '2-3 day'],
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'YES', 'description' => 'Besok', 'cost' => 35_000, 'etd' => '1 day'],
        ]])]);

        $this->buyer = User::factory()->create();
        $this->address = Address::factory()->for($this->buyer)->primary()->create(['recipient_name' => 'Budi']);

        $this->store = Store::factory()->active()->create();
        $this->store->setShippingOrigin(new Destination(23001, 'ASAL', 'JAWA BARAT', 'BANDUNG', 'COBLONG', 'DAGO', '40132'));
        $this->store->save();

        $product = Product::factory()->active()->for($this->store)->create(['name' => 'Kaos Polos']);
        $this->kaos = ProductVariant::factory()->for($product)->create([
            'price' => 50_000, 'stock' => 5, 'weight_grams' => 200, 'sku' => 'KAOS-1',
        ]);
        $this->buyer->cartItems()->create(['product_variant_id' => $this->kaos->id, 'quantity' => 2]);
    }

    /**
     * What the checkout page would send: 2 × 50.000 + JNE REG 18.000.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'address_id' => $this->address->id,
            'shipping' => [$this->store->id => 'jne:REG'],
            'idempotency_key' => (string) Str::uuid(),
            'expected_total' => 118_000,
        ], $overrides);
    }

    private function place(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->buyer)->post(route('checkout.store'), $this->payload($overrides));
    }

    public function test_placing_an_order_freezes_prices_takes_stock_and_empties_the_cart(): void
    {
        $this->freezeSecond();

        $response = $this->place()->assertSessionHasNoErrors();

        $checkout = Checkout::query()->sole();
        $response->assertRedirect(route('checkout.placed', $checkout->code));
        $this->assertMatchesRegularExpression('/^ZTG-[A-Z2-9]{8}$/', $checkout->code);
        $this->assertSame(100_000, $checkout->items_total);
        $this->assertSame(18_000, $checkout->shipping_total);
        $this->assertSame(118_000, $checkout->grand_total);
        $this->assertTrue(now()->addDay()->equalTo($checkout->expires_at));

        $order = Order::query()->sole();
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertSame("{$checkout->code}-1", $order->number);
        $this->assertSame('jne', $order->courier);
        $this->assertSame(400, $order->weight_grams);
        $this->assertSame('Budi', $order->recipient_name);

        $item = $order->items()->sole();
        $this->assertSame(['Kaos Polos', 'KAOS-1', 50_000, 2], [$item->product_name, $item->sku, $item->price, $item->quantity]);

        $this->assertSame(3, $this->kaos->fresh()?->stock);
        $this->assertSame(0, $this->buyer->cartItems()->count());
    }

    public function test_the_order_keeps_its_snapshot_when_the_product_and_address_change(): void
    {
        $this->place();

        $this->kaos->update(['price' => 99_000]);
        $this->kaos->product->update(['name' => 'Nama Baru']);
        $this->address->update(['recipient_name' => 'Orang Lain']);

        $order = Order::query()->sole();
        $this->assertSame('Budi', $order->recipient_name);
        $this->assertSame('Kaos Polos', $order->items()->sole()->product_name);
        $this->assertSame(50_000, $order->items()->sole()->price);
    }

    public function test_the_same_request_twice_creates_one_checkout(): void
    {
        $payload = $this->payload();

        $this->actingAs($this->buyer)->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->buyer)->post(route('checkout.store'), $payload)
            ->assertRedirect(route('checkout.placed', Checkout::query()->sole()->code));

        $this->assertSame(1, Checkout::query()->count());
        $this->assertSame(3, $this->kaos->fresh()?->stock, 'Stock taken once.');
    }

    public function test_a_total_that_no_longer_matches_is_refused(): void
    {
        // The seller raised the price after the buyer opened the page.
        $this->kaos->update(['price' => 60_000]);

        $this->place()->assertSessionHasErrors('checkout');

        $this->assertSame(0, Checkout::query()->count());
        $this->assertSame(5, $this->kaos->fresh()?->stock);
        $this->assertSame(1, $this->buyer->cartItems()->count(), 'Cart untouched.');
    }

    public function test_the_shipping_price_cannot_come_from_the_browser(): void
    {
        // An invented option, and a total that would fit a cheap one.
        $this->place(['shipping' => [$this->store->id => 'jne:GRATIS'], 'expected_total' => 100_000])
            ->assertSessionHasErrors(['checkout' => 'Pilih kurir untuk pesanan dari '.$this->store->name.'.']);

        // A real, more expensive option is charged at the SERVER's price.
        $this->place(['shipping' => [$this->store->id => 'jne:YES'], 'expected_total' => 135_000])->assertSessionHasNoErrors();
        $this->assertSame(35_000, Checkout::query()->sole()->shipping_total);
    }

    public function test_stock_bought_by_someone_else_first_cannot_be_sold_twice(): void
    {
        // Another buyer takes 4 of the 5 before our buyer submits.
        $other = User::factory()->create();
        Address::factory()->for($other)->primary()->create();
        $other->cartItems()->create(['product_variant_id' => $this->kaos->id, 'quantity' => 4]);
        $this->actingAs($other)->post(route('checkout.store'), [
            'address_id' => $other->addresses()->value('id'),
            'shipping' => [$this->store->id => 'jne:REG'],
            'idempotency_key' => (string) Str::uuid(),
            'expected_total' => 218_000,
        ])->assertSessionHasNoErrors();

        // Our buyer still wants 2, only 1 is left.
        $this->place()->assertSessionHasErrors('checkout');

        $this->assertSame(1, $this->kaos->fresh()?->stock);
        $this->assertSame(1, Checkout::query()->count());
    }

    public function test_stock_taken_between_the_preview_and_the_transaction_is_caught(): void
    {
        // Simulates the race: the preview (outside the transaction) still
        // sees 5 in stock, then another checkout takes 4 before our
        // transaction locks the row.
        $variantId = $this->kaos->id;
        $this->app->instance(CheckoutPreview::class, new class(app(ShippingProvider::class), $variantId) extends CheckoutPreview
        {
            public function __construct(ShippingProvider $shipping, private int $variantId)
            {
                parent::__construct($shipping);
            }

            public function build(User $user, ?Address $address): array
            {
                $preview = parent::build($user, $address);
                ProductVariant::query()->whereKey($this->variantId)->update(['stock' => 1]);

                return $preview;
            }
        });

        $this->place()->assertSessionHasErrors(['checkout' => 'Stok Kaos Polos baru saja habis atau berkurang.']);

        $this->assertSame(0, Checkout::query()->count(), 'Rolled back: no checkout without stock.');
        $this->assertSame(1, $this->kaos->fresh()?->stock);
    }

    public function test_someone_elses_address_cannot_be_used(): void
    {
        $foreign = Address::factory()->primary()->create();

        $this->place(['address_id' => $foreign->id])->assertNotFound();
        $this->assertSame(0, Checkout::query()->count());
    }

    public function test_unverified_accounts_cannot_place_orders(): void
    {
        $this->buyer->forceFill(['email_verified_at' => null])->save();

        $this->place()->assertRedirect(route('verification.notice'));
        $this->assertSame(0, Checkout::query()->count());
    }

    public function test_only_the_buyer_can_see_the_placed_page(): void
    {
        $this->place();
        $code = Checkout::query()->sole()->code;

        $this->actingAs($this->buyer)->get(route('checkout.placed', $code))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/checkout-placed')
                ->where('checkout.grand_total', 118_000)
                ->where('checkout.orders.0.items.0.name', 'Kaos Polos'));

        $this->actingAs(User::factory()->create())->get(route('checkout.placed', $code))->assertNotFound();
    }

    public function test_the_database_refuses_a_total_that_does_not_add_up(): void
    {
        $this->place();
        $checkout = Checkout::query()->sole();

        $this->expectException(QueryException::class);
        $checkout->grand_total = 1;
        $checkout->save();
    }
}
