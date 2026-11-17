<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartPageTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->create();
    }

    private function variant(int $price = 10_000, int $stock = 10, ?Store $store = null): ProductVariant
    {
        $product = Product::factory()->active()->for($store ?? Store::factory()->active())->create();

        return ProductVariant::factory()->for($product)->create(['price' => $price, 'stock' => $stock]);
    }

    private function addItem(ProductVariant $variant, int $quantity, ?int $seenPrice = null): CartItem
    {
        $item = $this->buyer->cartItems()->make([
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);
        $item->seen_price = $seenPrice ?? $variant->price;
        $item->save();

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function cart(): array
    {
        /** @var array<string, mixed> */
        return $this->actingAs($this->buyer)->get(route('cart.index'))->assertOk()->inertiaProps('cart');
    }

    public function test_guests_must_log_in(): void
    {
        $this->get(route('cart.index'))->assertRedirect(route('login'));
    }

    public function test_items_are_grouped_per_store_with_subtotals(): void
    {
        $tokoA = Store::factory()->active()->create(['name' => 'Toko A']);
        $this->addItem($this->variant(10_000, store: $tokoA), 2);
        $this->addItem($this->variant(5_000, store: $tokoA), 1);
        $this->addItem($this->variant(30_000), 1);

        $cart = $this->cart();

        $this->assertCount(2, $cart['stores']);
        $subtotals = collect($cart['stores'])->pluck('subtotal', 'name');
        $this->assertSame(25_000, $subtotals['Toko A']);
        $this->assertSame(55_000, $cart['total']);
        $this->assertSame(4, $cart['count']);
        $this->assertFalse($cart['has_problems']);
    }

    /**
     * @return array<string, array{\Closure(ProductVariant): void, string}>
     */
    public static function problems(): array
    {
        return [
            'product now draft' => [fn (ProductVariant $v) => $v->product->forceFill(['status' => ProductStatus::Draft])->save(), 'unavailable'],
            'product deleted' => [fn (ProductVariant $v) => $v->product->delete(), 'unavailable'],
            'variant removed' => [fn (ProductVariant $v) => $v->update(['is_active' => false]), 'unavailable'],
            'store suspended' => [fn (ProductVariant $v) => $v->product->store->forceFill(['status' => StoreStatus::Suspended])->save(), 'unavailable'],
            'sold out' => [fn (ProductVariant $v) => $v->update(['stock' => 0]), 'sold_out'],
            'stock below quantity' => [fn (ProductVariant $v) => $v->update(['stock' => 2]), 'not_enough'],
        ];
    }

    /**
     * @param  \Closure(ProductVariant): void  $change
     */
    #[DataProvider('problems')]
    public function test_changes_since_adding_are_flagged_and_not_counted(\Closure $change, string $status): void
    {
        $variant = $this->variant(10_000, 10);
        $this->addItem($variant, 3);
        $this->addItem($this->variant(7_000), 1); // a healthy item next to it

        $change($variant);
        $cart = $this->cart();

        $row = collect($cart['stores'])->flatMap(fn (array $s) => $s['items'])->firstWhere('quantity', 3);
        $this->assertSame($status, $row['status']);
        $this->assertSame(7_000, $cart['total'], 'Problem items are not in the total.');
        $this->assertTrue($cart['has_problems']);
        if ($status === 'unavailable') {
            $this->assertNull($row['href'], 'No link to a page that would 404.');
        }
    }

    public function test_a_price_change_since_adding_is_reported(): void
    {
        $variant = $this->variant(10_000);
        $this->addItem($variant, 1, seenPrice: 8_000);

        $row = $this->cart()['stores'][0]['items'][0];
        $this->assertSame(10_000, $row['price'], 'Billing uses today\'s price.');
        $this->assertSame(8_000, $row['seenPrice']);
    }

    public function test_changing_the_quantity_respects_stock_and_refreshes_the_seen_price(): void
    {
        $variant = $this->variant(10_000, 5);
        $item = $this->addItem($variant, 1, seenPrice: 8_000);

        $this->actingAs($this->buyer)->patch(route('cart.update', $item->id), ['quantity' => 4])->assertSessionHasNoErrors();
        $this->assertSame(4, $item->fresh()?->quantity);
        $this->assertSame(10_000, $item->fresh()?->seen_price);

        $this->actingAs($this->buyer)->patch(route('cart.update', $item->id), ['quantity' => 6])
            ->assertSessionHasErrors(['quantity' => 'Stok tersisa 5.']);
        $this->assertSame(4, $item->fresh()?->quantity);
    }

    public function test_items_can_be_removed(): void
    {
        $item = $this->addItem($this->variant(), 1);

        $this->actingAs($this->buyer)->delete(route('cart.destroy', $item->id))->assertRedirect();

        $this->assertNull($item->fresh());
    }

    public function test_nobody_can_touch_another_buyers_cart(): void
    {
        $item = $this->addItem($this->variant(), 1);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->patch(route('cart.update', $item->id), ['quantity' => 2])->assertNotFound();
        $this->actingAs($intruder)->delete(route('cart.destroy', $item->id))->assertNotFound();

        $this->assertSame(1, $item->fresh()?->quantity);
    }

    public function test_the_header_badge_counts_items(): void
    {
        $this->addItem($this->variant(), 2);
        $this->addItem($this->variant(), 3);

        $this->actingAs($this->buyer)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('cartCount', 5));

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('cartCount', 5)); // still logged in
        auth()->logout();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('cartCount', 0));
    }
}
