<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->active()->create(['slug' => 'toko-contoh']);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $variants  [price, stock]
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, array $variants = [[50_000, 5]], array $attributes = [], ?Category $category = null): Product
    {
        $product = Product::factory()
            ->active()
            ->for($this->store)
            ->for($category ?? Category::factory())
            ->create(['name' => $name, ...$attributes]);

        foreach ($variants as [$price, $stock]) {
            ProductVariant::factory()->for($product)->create(['price' => $price, 'stock' => $stock, 'options' => ['N' => (string) $price]]);
        }

        return $product;
    }

    // --- Home ---------------------------------------------------------------

    public function test_home_lists_only_visible_products_newest_first(): void
    {
        $this->product('Lama', attributes: ['created_at' => now()->subDay()]);
        $this->product('Baru');
        $this->product('Draf')->forceFill(['status' => 'draft'])->save();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/home')
                ->has('products', 2)
                ->where('products.0.name', 'Baru')
                ->where('products.1.name', 'Lama'));
    }

    public function test_a_card_shows_the_cheapest_variant_that_is_in_stock(): void
    {
        $product = $this->product('Kaos', [[40_000, 0], [55_000, 3], [60_000, 9]]);
        ProductImage::factory()->for($product)->create();

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.0.price', 55_000)
                ->where('products.0.inStock', true)
                ->where('products.0.href', '/toko/toko-contoh/'.$product->slug)
                ->whereNot('products.0.imageUrl', null));
    }

    public function test_a_card_says_sold_out_when_every_variant_is(): void
    {
        $this->product('Habis', [[40_000, 0], [30_000, 0]]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.0.price', 30_000)
                ->where('products.0.inStock', false));
    }

    public function test_the_grid_does_not_run_a_query_per_product(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('home'))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->product('Satu');
        $withOne = $count();

        foreach (range(1, 8) as $i) {
            ProductImage::factory()->for($this->product("Produk {$i}"))->create();
        }

        $this->assertSame($withOne, $count(), 'Query count must not grow with the number of products (N+1).');
    }

    // --- Category -----------------------------------------------------------

    public function test_a_category_page_includes_its_active_subcategories(): void
    {
        $fashion = Category::factory()->create(['slug' => 'fashion']);
        $pria = Category::factory()->create(['parent_id' => $fashion->id]);
        $kaos = Category::factory()->create(['parent_id' => $pria->id]);
        $hidden = Category::factory()->create(['parent_id' => $fashion->id, 'is_active' => false]);

        $this->product('Di Fashion', category: $fashion);
        $this->product('Di Kaos (cucu)', category: $kaos);
        $this->product('Di kategori nonaktif', category: $hidden);
        $this->product('Di kategori lain');

        $this->get(route('shop.categories.show', 'fashion'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/category')
                ->where('products.total', 2)
                ->has('subcategories', 1));
    }

    public function test_hidden_categories_are_not_found(): void
    {
        $parent = Category::factory()->create(['is_active' => false]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'slug' => 'anak']);

        $this->get(route('shop.categories.show', $parent->slug))->assertNotFound();
        $this->get(route('shop.categories.show', 'anak'))->assertNotFound();
        $this->get(route('shop.categories.show', 'tidak-ada'))->assertNotFound();
        $this->assertTrue($child->exists);
    }

    public function test_products_can_be_sorted_by_price(): void
    {
        $category = Category::factory()->create(['slug' => 'semua']);
        $this->product('Sedang', [[50_000, 1]], category: $category);
        $this->product('Murah', [[10_000, 1], [90_000, 1]], category: $category);
        $this->product('Mahal', [[70_000, 1]], category: $category);

        $names = fn (string $sort) => collect(
            $this->get(route('shop.categories.show', ['semua', 'sort' => $sort]))->inertiaProps('products.data'),
        )->pluck('name')->all();

        // "Murah" costs its cheapest variant (10.000), not 90.000.
        $this->assertSame(['Murah', 'Sedang', 'Mahal'], $names('termurah'));
        $this->assertSame(['Mahal', 'Sedang', 'Murah'], $names('termahal'));
    }

    public function test_an_unknown_sort_falls_back_to_newest(): void
    {
        $category = Category::factory()->create(['slug' => 'semua']);
        $this->product('A', category: $category);

        $this->get(route('shop.categories.show', ['semua', 'sort' => 'DROP TABLE']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('sort', 'terbaru'));
    }

    // --- Search -------------------------------------------------------------

    public function test_search_is_case_insensitive_and_skips_hidden_products(): void
    {
        $this->product('Kaos Polos Hitam');
        $this->product('Celana Kaos');
        $this->product('Kaos Draf')->forceFill(['status' => 'draft'])->save();
        $this->product('Topi');

        $this->get(route('shop.search', ['q' => 'KAOS']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/search')
                ->where('products.total', 2));
    }

    public function test_search_treats_wildcards_as_text(): void
    {
        // "100%" finding "1000" is now intended (typo tolerance), so the
        // escape is checked where it matters: a lone "%" must not become
        // "match everything".
        $this->product('Diskon 100% Asli');
        $this->product('Diskon 1000 Asli');
        $this->product('Topi Rimba');

        $this->get(route('shop.search', ['q' => '%']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.total', 1)
                ->where('products.data.0.name', 'Diskon 100% Asli'));
    }

    public function test_search_tolerates_typos_and_puts_the_best_match_first(): void
    {
        $this->product('Celana Kaos Olahraga', attributes: ['created_at' => now()]);
        $this->product('Kaos Polos Hitam', attributes: ['created_at' => now()->subDay()]);
        $this->product('Sepatu Lari Pria');

        $this->get(route('shop.search', ['q' => 'kaoss polso']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sort', 'relevan')
                ->where('products.total', 1)
                ->where('products.data.0.name', 'Kaos Polos Hitam'));

        // Both contain "kaos"; the closer match comes first, not the newer one.
        $names = collect($this->get(route('shop.search', ['q' => 'kaos polos']))->inertiaProps('products.data'))->pluck('name')->all();
        $this->assertSame('Kaos Polos Hitam', $names[0]);

        // Other orders still work on search results.
        $this->get(route('shop.search', ['q' => 'kaos', 'sort' => 'terbaru']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sort', 'terbaru')
                ->where('products.data.0.name', 'Celana Kaos Olahraga'));
    }

    public function test_an_empty_search_runs_no_product_query(): void
    {
        $this->get(route('shop.search', ['q' => '   ']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('q', '')
                ->where('products', null));
    }
}
