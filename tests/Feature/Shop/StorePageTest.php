<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorePageTest extends TestCase
{
    use RefreshDatabase;

    private function sellable(Store $store, string $name): Product
    {
        return Product::factory()->active()->for($store)
            ->has(ProductVariant::factory(), 'variants')
            ->create(['name' => $name]);
    }

    public function test_a_store_page_lists_only_its_visible_products(): void
    {
        $store = Store::factory()->active()->create(['slug' => 'toko-contoh', 'name' => 'Toko Contoh']);
        $this->sellable($store, 'Dijual');
        $this->sellable($store, 'Draf')->forceFill(['status' => ProductStatus::Draft])->save();
        $this->sellable(Store::factory()->active()->create(), 'Punya toko lain');

        $this->get(route('shop.stores.show', 'toko-contoh'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/store')
                ->where('store.name', 'Toko Contoh')
                ->where('products.total', 1)
                ->where('products.data.0.name', 'Dijual'));
    }

    public function test_pending_and_suspended_stores_are_not_found(): void
    {
        Store::factory()->create(['slug' => 'menunggu']);
        Store::factory()->suspended()->create(['slug' => 'ditangguhkan']);

        $this->get(route('shop.stores.show', 'menunggu'))->assertNotFound();
        $this->get(route('shop.stores.show', 'ditangguhkan'))->assertNotFound();
        $this->get(route('shop.stores.show', 'tidak-ada'))->assertNotFound();
    }

    public function test_the_seller_gets_a_public_link_only_while_buyers_can_see_the_product(): void
    {
        $store = Store::factory()->active()->create(['slug' => 'toko-contoh']);
        $product = $this->sellable($store, 'Kaos');
        $edit = fn () => $this->actingAs($store->user)->get(route('seller.products.edit', $product));

        $edit()->assertInertia(fn (Assert $page) => $page
            ->where('publicUrl', "/toko/toko-contoh/{$product->slug}"));

        $product->forceFill(['status' => ProductStatus::Draft])->save();

        $edit()->assertInertia(fn (Assert $page) => $page->where('publicUrl', null));
    }
}
