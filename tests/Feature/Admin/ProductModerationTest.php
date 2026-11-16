<?php

namespace Tests\Feature\Admin;

use App\Actions\Products\UpdateProduct;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function activeProduct(string $name = 'Kaos Polos', ?Store $store = null): Product
    {
        return Product::factory()
            ->active()
            ->for($store ?? Store::factory()->active())
            ->has(ProductVariant::factory(), 'variants')
            ->create(['name' => $name]);
    }

    public function test_only_admins_can_moderate(): void
    {
        $product = $this->activeProduct();
        $seller = $product->store->user;

        $this->actingAs($seller)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($seller)->post(route('admin.products.ban', $product), ['reason' => 'Mencoba memblokir sendiri.'])->assertForbidden();

        $this->assertSame(ProductStatus::Active, $product->fresh()?->status);
    }

    public function test_admin_bans_a_product_with_a_reason(): void
    {
        $this->freezeSecond();
        $product = $this->activeProduct();

        $this->actingAs($this->admin)
            ->post(route('admin.products.ban', $product), ['reason' => 'Foto mengandung merek palsu.'])
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(ProductStatus::Banned, $product->status);
        $this->assertSame('Foto mengandung merek palsu.', $product->ban_reason);
        $this->assertTrue(now()->equalTo($product->banned_at));
    }

    public function test_a_ban_needs_a_real_reason(): void
    {
        $product = $this->activeProduct();

        $this->actingAs($this->admin)
            ->post(route('admin.products.ban', $product), ['reason' => 'jelek'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(ProductStatus::Active, $product->fresh()?->status);
    }

    public function test_banning_twice_keeps_the_first_reason(): void
    {
        $product = $this->activeProduct();

        $this->actingAs($this->admin)->post(route('admin.products.ban', $product), ['reason' => 'Alasan pertama yang jelas.']);
        $this->actingAs($this->admin)->post(route('admin.products.ban', $product), ['reason' => 'Alasan kedua menimpa?']);

        $this->assertSame('Alasan pertama yang jelas.', $product->fresh()?->ban_reason);
    }

    public function test_unbanning_returns_the_product_to_draft(): void
    {
        $product = $this->activeProduct();
        $this->actingAs($this->admin)->post(route('admin.products.ban', $product), ['reason' => 'Deskripsi menyesatkan.']);

        $this->actingAs($this->admin)->post(route('admin.products.unban', $product))->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(ProductStatus::Draft, $product->status, 'Seller re-checks before it goes on sale again.');
        $this->assertNull($product->ban_reason);
        $this->assertNull($product->banned_at);
        $this->assertTrue($product->store->user->can('update', $product), 'Seller can edit it again.');
    }

    public function test_unbanning_a_product_that_is_not_banned_changes_nothing(): void
    {
        $product = $this->activeProduct();

        $this->actingAs($this->admin)->post(route('admin.products.unban', $product));

        $this->assertSame(ProductStatus::Active, $product->fresh()?->status);
    }

    public function test_a_seller_save_cannot_overwrite_a_ban_that_happened_meanwhile(): void
    {
        $product = $this->activeProduct();
        // The seller's request passed the policy with this (still active) copy...
        $stale = $product->fresh();
        // ...then an admin banned it before the save ran.
        $this->actingAs($this->admin)->post(route('admin.products.ban', $product), ['reason' => 'Produk terlarang dijual.']);

        try {
            app(UpdateProduct::class)->handle($stale, [
                'name' => 'Diubah penjual',
                'category_id' => $product->category_id,
                'status' => 'active',
                'options' => [],
                'variants' => [['options' => [], 'price' => 10_000, 'stock' => 1, 'weight_grams' => 100]],
            ]);
            $this->fail('The save should have been refused.');
        } catch (AuthorizationException) {
            // expected
        }

        $product->refresh();
        $this->assertSame(ProductStatus::Banned, $product->status);
        $this->assertSame('Kaos Polos', $product->name);
    }

    public function test_the_seller_sees_the_ban_reason(): void
    {
        $product = $this->activeProduct();
        $this->actingAs($this->admin)->post(route('admin.products.ban', $product), ['reason' => 'Foto mengandung merek palsu.']);

        $this->actingAs($product->store->user)
            ->get(route('seller.products.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.data.0.status', 'banned')
                ->where('products.data.0.ban_reason', 'Foto mengandung merek palsu.'));
    }

    public function test_list_filters_by_status_and_searches_product_or_store_name(): void
    {
        $this->activeProduct('Kaos Polos');
        $this->activeProduct('Sepatu Lari', Store::factory()->active()->create(['name' => 'Toko Kaos Murah']));
        $this->activeProduct('Topi Rimba');
        Product::factory()->create(['name' => 'Kaos Draf']); // draft: other tab

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['q' => 'kaos']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/index')
                ->where('status', 'active')
                ->has('products.data', 2) // "Kaos Polos" + product of "Toko Kaos Murah"
                ->where('tabs.0.value', 'draft')
                ->where('tabs.0.count', 1)
                ->where('tabs.1.count', 3));
    }

    public function test_search_treats_percent_and_underscore_as_text(): void
    {
        $this->activeProduct('Diskon 100% Asli');
        $this->activeProduct('Diskon 1000 Asli');

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['q' => '100%']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Diskon 100% Asli'));
    }
}
