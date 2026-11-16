<?php

namespace Tests\Feature\Seller;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UpdateProductTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->active()->create();
        $this->category = Category::factory()->create();

        // "Kaos" with Warna × Ukuran = 4 variants, created through the real
        // endpoint so the starting point is exactly what sellers produce.
        $this->actingAs($this->store->user)
            ->post(route('seller.products.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->product = Product::query()->sole();
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int}>  $combos  [warna, ukuran, price]
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $combos = [['Hitam', 'M', 75_000], ['Hitam', 'L', 80_000], ['Putih', 'M', 75_000], ['Putih', 'L', 80_000]], array $overrides = []): array
    {
        $warna = array_values(array_unique(array_column($combos, 0)));
        $ukuran = array_values(array_unique(array_column($combos, 1)));

        return array_replace([
            'name' => 'Kaos Polos',
            'category_id' => $this->category->id,
            'description' => null,
            'status' => 'active',
            'options' => [
                ['name' => 'Warna', 'values' => $warna],
                ['name' => 'Ukuran', 'values' => $ukuran],
            ],
            'variants' => array_map(fn (array $c) => [
                'options' => ['Warna' => $c[0], 'Ukuran' => $c[1]],
                'sku' => null, 'price' => $c[2], 'compare_at_price' => null, 'stock' => 5, 'weight_grams' => 200,
            ], $combos),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function update(array $payload): TestResponse
    {
        return $this->actingAs($this->store->user)
            ->put(route('seller.products.update', $this->product), $payload);
    }

    private function variant(string $warna, string $ukuran): ProductVariant
    {
        return $this->product->variants()
            ->where('options->Warna', $warna)
            ->where('options->Ukuran', $ukuran)
            ->sole();
    }

    // --- Access -------------------------------------------------------------

    public function test_the_edit_form_shows_options_in_the_sellers_order(): void
    {
        $this->actingAs($this->store->user)
            ->get(route('seller.products.edit', $this->product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('seller/products/edit')
                // jsonb sorts object keys ("Ukuran" before "Warna"); the
                // options column keeps the order the seller typed.
                ->where('product.options.0.name', 'Warna')
                ->where('product.options.1.name', 'Ukuran')
                ->has('product.variants', 4));
    }

    public function test_sellers_cannot_edit_another_stores_product(): void
    {
        $intruder = Store::factory()->active()->create()->user;

        $this->actingAs($intruder)->get(route('seller.products.edit', $this->product))->assertForbidden();
        $this->actingAs($intruder)->put(route('seller.products.update', $this->product), $this->payload())->assertForbidden();
    }

    public function test_a_banned_product_is_locked_for_its_seller(): void
    {
        $this->product->status = ProductStatus::Banned;
        $this->product->save();

        $this->actingAs($this->store->user)->get(route('seller.products.edit', $this->product))->assertForbidden();
        $this->update($this->payload())->assertForbidden();
        $this->actingAs($this->store->user)->get(route('seller.products.images.index', $this->product))->assertForbidden();

        $this->assertSame(ProductStatus::Banned, $this->product->fresh()?->status);
    }

    // --- Product fields -----------------------------------------------------

    public function test_renaming_keeps_the_slug_so_old_links_keep_working(): void
    {
        $this->update($this->payload(overrides: ['name' => 'Kaos Polos Premium']))
            ->assertRedirect(route('seller.products.edit', $this->product));

        $product = $this->product->fresh();
        $this->assertSame('Kaos Polos Premium', $product?->name);
        $this->assertSame('kaos-polos', $product?->slug);
    }

    public function test_sellers_can_archive_but_never_ban(): void
    {
        $this->update($this->payload(overrides: ['status' => 'archived']))->assertSessionHasNoErrors();
        $this->assertSame(ProductStatus::Archived, $this->product->fresh()?->status);

        $this->update($this->payload(overrides: ['status' => 'banned']))->assertSessionHasErrors('status');
        $this->assertSame(ProductStatus::Archived, $this->product->fresh()?->status);
    }

    public function test_a_deactivated_category_may_be_kept_but_not_chosen(): void
    {
        $this->category->update(['is_active' => false]);
        $other = Category::factory()->create(['is_active' => false]);

        $this->update($this->payload())->assertSessionHasNoErrors();
        $this->update($this->payload(overrides: ['category_id' => $other->id]))->assertSessionHasErrors('category_id');
    }

    // --- Variants (the upsert) ----------------------------------------------

    public function test_editing_a_price_updates_the_same_variant_row(): void
    {
        $before = $this->variant('Hitam', 'M');

        $this->update($this->payload([['Hitam', 'M', 99_000], ['Hitam', 'L', 80_000], ['Putih', 'M', 75_000], ['Putih', 'L', 80_000]]))
            ->assertSessionHasNoErrors();

        $after = $this->variant('Hitam', 'M');
        $this->assertSame($before->id, $after->id, 'Variant must keep its id: carts and orders point at it.');
        $this->assertSame(99_000, $after->price);
        $this->assertSame(4, $this->product->variants()->count());
    }

    public function test_removed_variants_are_deactivated_not_deleted(): void
    {
        $putihL = $this->variant('Putih', 'L');

        $this->update($this->payload([['Hitam', 'M', 75_000], ['Hitam', 'L', 80_000], ['Putih', 'M', 75_000]]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($putihL->fresh()?->is_active);
        $this->assertSame(3, $this->product->activeVariants()->count());
        $this->assertSame(4, $this->product->variants()->count());
    }

    public function test_adding_back_a_removed_combination_reactivates_it(): void
    {
        $putihL = $this->variant('Putih', 'L');
        $this->update($this->payload([['Hitam', 'M', 75_000], ['Hitam', 'L', 80_000], ['Putih', 'M', 75_000]]));

        $this->update($this->payload())->assertSessionHasNoErrors();

        $this->assertTrue($putihL->fresh()?->is_active);
        $this->assertSame(4, $this->product->variants()->count(), 'No duplicate row for the same combination.');
    }

    public function test_a_new_value_adds_new_variants(): void
    {
        $this->update($this->payload([['Hitam', 'M', 75_000], ['Hitam', 'L', 80_000], ['Putih', 'M', 75_000], ['Putih', 'L', 80_000], ['Navy', 'M', 75_000], ['Navy', 'L', 80_000]]))
            ->assertSessionHasNoErrors();

        $this->assertSame(6, $this->product->activeVariants()->count());
        $this->assertSame(['Hitam', 'Putih', 'Navy'], $this->product->fresh()?->options[0]['values']);
    }

    public function test_option_order_in_the_form_does_not_matter_for_matching(): void
    {
        $before = $this->variant('Hitam', 'M');

        // Same combinations, but the seller moved "Ukuran" above "Warna".
        $payload = $this->payload();
        $payload['options'] = array_reverse($payload['options']);
        foreach ($payload['variants'] as $i => $variant) {
            $payload['variants'][$i]['options'] = array_reverse($variant['options'], true);
        }

        $this->update($payload)->assertSessionHasNoErrors();

        $this->assertSame($before->id, $this->variant('Hitam', 'M')->id);
        $this->assertSame('Ukuran', $this->product->fresh()?->options[0]['name']);
        $this->assertSame(4, $this->product->variants()->count());
    }

    public function test_two_variants_can_swap_skus(): void
    {
        $withSkus = function (string $a, string $b): array {
            $payload = $this->payload();
            $payload['variants'][0]['sku'] = $a;
            $payload['variants'][1]['sku'] = $b;

            return $payload;
        };

        $this->update($withSkus('KAOS-A', 'KAOS-B'))->assertSessionHasNoErrors();
        // Row by row this would collide on unique(product_id, sku) halfway.
        $this->update($withSkus('KAOS-B', 'KAOS-A'))->assertSessionHasNoErrors();

        $this->assertSame('KAOS-B', $this->variant('Hitam', 'M')->sku);
        $this->assertSame('KAOS-A', $this->variant('Hitam', 'L')->sku);
    }

    public function test_an_unchanged_sku_is_kept(): void
    {
        $payload = $this->payload();
        $payload['variants'][0]['sku'] = 'KAOS-HM';

        $this->update($payload)->assertSessionHasNoErrors();
        $this->update($payload)->assertSessionHasNoErrors();

        $this->assertSame('KAOS-HM', $this->variant('Hitam', 'M')->sku);
    }

    public function test_a_new_variant_may_take_the_sku_of_a_removed_one(): void
    {
        $payload = $this->payload();
        $payload['variants'][3]['sku'] = 'KAOS-X';
        $this->update($payload)->assertSessionHasNoErrors();

        // Putih/L (holding KAOS-X) removed, Navy/M added with KAOS-X.
        $payload = $this->payload([['Hitam', 'M', 75_000], ['Hitam', 'L', 80_000], ['Putih', 'M', 75_000], ['Navy', 'M', 75_000]]);
        $payload['variants'][3]['sku'] = 'KAOS-X';
        $this->update($payload)->assertSessionHasNoErrors();

        $this->assertSame('KAOS-X', $this->variant('Navy', 'M')->sku);
    }

    public function test_the_product_list_counts_only_active_variants(): void
    {
        $this->update($this->payload([['Hitam', 'M', 75_000], ['Putih', 'M', 90_000]]));

        $this->actingAs($this->store->user)
            ->get(route('seller.products.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.data.0.variants_count', 2)
                ->where('products.data.0.min_price', 75_000)
                ->where('products.data.0.max_price', 90_000)
                ->where('products.data.0.total_stock', 10));
    }
}
