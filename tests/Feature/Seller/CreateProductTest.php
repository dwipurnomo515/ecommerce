<?php

namespace Tests\Feature\Seller;

use App\Actions\Products\CreateProduct;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreateProductTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->active()->create();
        $this->category = Category::factory()->create();
    }

    /**
     * A valid simple product (no options, one variant). Tests override parts.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Kaos Polos Katun',
            'category_id' => $this->category->id,
            'description' => 'Kaos katun combed 30s.',
            'status' => 'active',
            'options' => [],
            'variants' => [
                ['options' => [], 'sku' => null, 'price' => 75_000, 'compare_at_price' => null, 'stock' => 10, 'weight_grams' => 200],
            ],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadWithVariants(): array
    {
        $variant = fn (string $warna, string $ukuran, int $price) => [
            'options' => ['Warna' => $warna, 'Ukuran' => $ukuran],
            'sku' => null, 'price' => $price, 'compare_at_price' => null, 'stock' => 5, 'weight_grams' => 200,
        ];

        return $this->payload([
            'options' => [
                ['name' => 'Warna', 'values' => ['Hitam', 'Putih']],
                ['name' => 'Ukuran', 'values' => ['M', 'L']],
            ],
            'variants' => [
                $variant('Hitam', 'M', 75_000),
                $variant('Hitam', 'L', 80_000),
                $variant('Putih', 'M', 75_000),
                $variant('Putih', 'L', 80_000),
            ],
        ]);
    }

    // --- Access -------------------------------------------------------------

    public function test_only_approved_stores_can_add_products(): void
    {
        $pending = Store::factory()->create();
        $noStore = User::factory()->create();

        $this->actingAs($pending->user)->get(route('seller.products.create'))->assertForbidden();
        $this->actingAs($pending->user)->post(route('seller.products.store'), $this->payload())->assertForbidden();
        $this->actingAs($noStore)->post(route('seller.products.store'), $this->payload())->assertForbidden();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_the_product_list_only_shows_my_own_products(): void
    {
        Product::factory()->for($this->store)->create(['name' => 'Milik saya']);
        Product::factory()->create(['name' => 'Milik toko lain']);

        $this->actingAs($this->store->user)
            ->get(route('seller.products.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('seller/products/index')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Milik saya')
                ->where('canCreate', true));
    }

    // --- Creating -----------------------------------------------------------

    public function test_a_simple_product_is_saved_with_one_variant(): void
    {
        $response = $this->actingAs($this->store->user)
            ->post(route('seller.products.store'), $this->payload())
            ->assertInertiaFlash('toast.type', 'success');

        $product = $this->store->products()->with('variants')->sole();
        $response->assertRedirect(route('seller.products.images.index', $product));
        $this->assertSame('kaos-polos-katun', $product->slug);
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertCount(1, $product->variants);
        $this->assertSame(75_000, $product->variants[0]->price);
        $this->assertSame([], $product->variants[0]->options);
    }

    public function test_a_product_with_colour_and_size_options_saves_every_combination(): void
    {
        $this->actingAs($this->store->user)
            ->post(route('seller.products.store'), $this->payloadWithVariants())
            ->assertSessionHasNoErrors();

        $variants = $this->store->products()->sole()->variants()->orderBy('id')->get();
        $this->assertCount(4, $variants);
        $this->assertSame(['Warna' => 'Putih', 'Ukuran' => 'L'], $variants[3]->options);
        $this->assertSame(80_000, $variants[3]->price);
    }

    public function test_a_second_product_with_the_same_name_gets_a_new_slug(): void
    {
        Product::factory()->for($this->store)->create(['slug' => 'kaos-polos-katun'])->delete();

        $this->actingAs($this->store->user)->post(route('seller.products.store'), $this->payload());

        $this->assertDatabaseHas('products', ['store_id' => $this->store->id, 'slug' => 'kaos-polos-katun-2']);
    }

    public function test_sellers_cannot_create_banned_or_archived_products_or_post_into_another_store(): void
    {
        $otherStore = Store::factory()->active()->create();

        $this->actingAs($this->store->user)
            ->post(route('seller.products.store'), $this->payload(['status' => 'banned', 'store_id' => $otherStore->id]))
            ->assertSessionHasErrors('status');

        $this->actingAs($this->store->user)
            ->post(route('seller.products.store'), $this->payload(['store_id' => $otherStore->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $otherStore->products()->count());
        $this->assertSame(1, $this->store->products()->count());
    }

    public function test_nothing_is_saved_when_one_variant_fails_to_insert(): void
    {
        $ok = ['options' => ['Ukuran' => 'M'], 'price' => 10_000, 'stock' => 1, 'weight_grams' => 100];
        // Bypasses the FormRequest on purpose: the DB CHECK (stock >= 0)
        // rejects this row after the product and the first variant are inserted.
        $broken = ['options' => ['Ukuran' => 'L'], 'price' => 10_000, 'stock' => -5, 'weight_grams' => 100];

        try {
            app(CreateProduct::class)->handle($this->store, [
                'name' => 'Produk Setengah Jadi',
                'category_id' => $this->category->id,
                'status' => 'draft',
                'options' => [['name' => 'Ukuran', 'values' => ['M', 'L']]],
                'variants' => [$ok, $broken],
            ]);
            $this->fail('The database should have rejected the negative stock.');
        } catch (QueryException) {
            // expected
        }

        // Note: RefreshDatabase wraps each test in a transaction, and the
        // action's DB::transaction becomes a SAVEPOINT inside it. Without the
        // action's transaction, PostgreSQL marks the whole test transaction as
        // aborted and these queries throw "current transaction is aborted".
        // Either way the test only passes when the action rolls back cleanly.
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
    }

    // --- Validation ---------------------------------------------------------

    public function test_prices_stock_and_weight_must_be_sensible(): void
    {
        $variant = ['options' => [], 'price' => 50, 'compare_at_price' => 40, 'stock' => -1, 'weight_grams' => 0];

        $this->actingAs($this->store->user)
            ->post(route('seller.products.store'), $this->payload(['variants' => [$variant]]))
            ->assertSessionHasErrors([
                'variants.0.price',
                'variants.0.compare_at_price',
                'variants.0.stock',
                'variants.0.weight_grams',
            ]);
    }

    public function test_the_strike_through_price_must_be_higher_than_the_selling_price(): void
    {
        $variant = ['options' => [], 'price' => 100_000, 'compare_at_price' => 90_000, 'stock' => 1, 'weight_grams' => 100];

        $this->actingAs($this->store->user)
            ->post(route('seller.products.store'), $this->payload(['variants' => [$variant]]))
            ->assertSessionHasErrors(['variants.0.compare_at_price' => 'Harga coret harus lebih tinggi dari harga jual.']);
    }

    public function test_a_product_without_options_cannot_have_several_prices(): void
    {
        $variant = ['options' => [], 'price' => 10_000, 'stock' => 1, 'weight_grams' => 100];

        $this->actingAs($this->store->user)
            ->post(route('seller.products.store'), $this->payload(['variants' => [$variant, $variant]]))
            ->assertSessionHasErrors('variants');
    }

    public function test_variants_must_match_the_declared_options(): void
    {
        $data = $this->payloadWithVariants();
        $data['variants'][0]['options'] = ['Warna' => 'Merah', 'Ukuran' => 'M']; // Merah not declared
        $data['variants'][1]['options'] = ['Warna' => 'Hitam'];                  // Ukuran missing
        $data['variants'][3]['options'] = ['Warna' => 'Putih', 'Ukuran' => 'M']; // duplicate of #2

        $this->actingAs($this->store->user)
            ->post(route('seller.products.store'), $data)
            ->assertSessionHasErrors(['variants.0.options', 'variants.1.options', 'variants.3.options']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_skus_must_be_unique_within_the_product(): void
    {
        $data = $this->payloadWithVariants();
        $data['variants'][0]['sku'] = 'KAOS-01';
        $data['variants'][2]['sku'] = 'kaos-01';

        $this->actingAs($this->store->user)
            ->post(route('seller.products.store'), $data)
            ->assertSessionHasErrors('variants.2.sku');
    }

    public function test_inactive_categories_and_their_children_cannot_be_chosen(): void
    {
        $hidden = Category::factory()->inactive()->create();
        $childOfHidden = Category::factory()->childOf($hidden)->create();

        foreach ([$hidden, $childOfHidden] as $category) {
            $this->actingAs($this->store->user)
                ->post(route('seller.products.store'), $this->payload(['category_id' => $category->id]))
                ->assertSessionHasErrors('category_id');
        }
    }
}
