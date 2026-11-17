<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Shipping\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const COST_URL = 'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost';

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.rajaongkir.key' => 'test-key']);
        $this->buyer = User::factory()->create();
    }

    private function store(?int $originId = 23001): Store
    {
        $store = Store::factory()->active()->create();

        if ($originId !== null) {
            $store->setShippingOrigin(new Destination($originId, 'ASAL', 'P', 'BANDUNG', 'D', 'S', '40132'));
            $store->save();
        }

        return $store;
    }

    private function inCart(Store $store, int $weight, int $quantity = 1, int $price = 10_000): ProductVariant
    {
        $product = Product::factory()->active()->for($store)->create();
        $variant = ProductVariant::factory()->for($product)->create(['weight_grams' => $weight, 'price' => $price, 'stock' => 10]);
        $this->buyer->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => $quantity]);

        return $variant;
    }

    private function address(int $destinationId = 17473, bool $primary = true): Address
    {
        $factory = Address::factory()->for($this->buyer);

        return ($primary ? $factory->primary() : $factory)->create(['destination_id' => $destinationId]);
    }

    private function fakeCosts(): void
    {
        Http::fake([self::COST_URL => Http::response(['meta' => ['code' => 200], 'data' => [
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 18_000, 'etd' => '2-3 day'],
            ['name' => 'SiCepat', 'code' => 'sicepat', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 16_000, 'etd' => '2 day'],
        ]])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkout(array $query = []): array
    {
        /** @var array<string, mixed> */
        return $this->actingAs($this->buyer)->get(route('checkout.show', $query))->assertOk()->inertiaProps('checkout');
    }

    public function test_guests_must_log_in(): void
    {
        $this->get(route('checkout.show'))->assertRedirect(route('login'));
    }

    public function test_each_store_is_quoted_with_its_origin_the_address_and_the_parcel_weight(): void
    {
        $this->fakeCosts();
        $store = $this->store(originId: 23001);
        $this->inCart($store, weight: 300, quantity: 2);
        $this->inCart($store, weight: 450);
        $this->address(destinationId: 17473);

        $checkout = $this->checkout();

        $parcel = $checkout['stores'][0];
        $this->assertSame(1050, $parcel['weight']); // 2×300 + 450
        $this->assertSame('ok', $parcel['shipping']['status']);
        $this->assertSame('sicepat:REG', $parcel['shipping']['options'][0]['key'], 'Cheapest first.');

        Http::assertSent(fn (Request $r) => $r['origin'] === 23001 && $r['destination'] === 17473 && $r['weight'] === 1050);
    }

    public function test_one_parcel_per_store(): void
    {
        $this->fakeCosts();
        // Different origins: same origin + destination + weight would
        // (correctly) be answered from the quote cache.
        $this->inCart($this->store(originId: 23001), 200, price: 10_000);
        $this->inCart($this->store(originId: 151), 200, price: 25_000);
        $this->address();

        $checkout = $this->checkout();

        $this->assertCount(2, $checkout['stores']);
        $this->assertSame(35_000, $checkout['items_total']);
        Http::assertSentCount(2);
    }

    public function test_without_an_address_nothing_is_quoted(): void
    {
        Http::fake();
        $this->inCart($this->store(), 200);

        $this->actingAs($this->buyer)->get(route('checkout.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('addressId', null)
                ->where('checkout.stores.0.shipping.status', 'no_address'));

        Http::assertNothingSent();
    }

    public function test_a_store_without_an_origin_is_explained_not_quoted(): void
    {
        Http::fake();
        $this->inCart($this->store(originId: null), 200);
        $this->address();

        $this->assertSame('no_origin', $this->checkout()['stores'][0]['shipping']['status']);
        Http::assertNothingSent();
    }

    public function test_a_shipping_outage_does_not_break_the_page(): void
    {
        Http::fake([self::COST_URL => Http::response(['meta' => ['code' => 500]], 500)]);
        $this->inCart($this->store(), 200);
        $this->address();

        $this->assertSame('unavailable', $this->checkout()['stores'][0]['shipping']['status']);
    }

    public function test_another_address_can_be_chosen_but_not_someone_elses(): void
    {
        $this->fakeCosts();
        $this->inCart($this->store(), 200);
        $this->address(destinationId: 17473);
        $office = $this->address(destinationId: 99999, primary: false);

        $this->checkout(['alamat' => $office->id]);
        Http::assertSent(fn (Request $r) => $r['destination'] === 99999);

        $foreign = Address::factory()->primary()->create();
        $this->actingAs($this->buyer)->get(route('checkout.show', ['alamat' => $foreign->id]))->assertNotFound();
    }

    public function test_items_that_cannot_be_bought_are_left_out(): void
    {
        $this->fakeCosts();
        $store = $this->store();
        $this->inCart($store, 200, price: 10_000);
        $gone = $this->inCart($store, 999, price: 50_000);
        $gone->product->forceFill(['status' => ProductStatus::Draft])->save();
        $this->address();

        $checkout = $this->checkout();

        $this->assertCount(1, $checkout['stores'][0]['items']);
        $this->assertSame(200, $checkout['stores'][0]['weight'], 'Weight of left-out items is not charged.');
        $this->assertSame(10_000, $checkout['items_total']);
        $this->assertTrue($checkout['has_problems']);
    }

    public function test_with_nothing_buyable_the_buyer_goes_back_to_the_cart(): void
    {
        $variant = $this->inCart($this->store(), 200);
        $variant->update(['stock' => 0]);

        $this->actingAs($this->buyer)->get(route('checkout.show'))->assertRedirect(route('cart.index'));

        CartItem::query()->delete();
        $this->actingAs($this->buyer)->get(route('checkout.show'))->assertRedirect(route('cart.index'));
    }
}
