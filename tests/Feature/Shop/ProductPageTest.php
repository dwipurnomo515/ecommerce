<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $fashion = Category::factory()->create(['name' => 'Fashion']);
        $kaos = Category::factory()->create(['name' => 'Kaos', 'parent_id' => $fashion->id]);

        $this->product = Product::factory()
            ->active()
            ->for(Store::factory()->active()->create(['name' => 'Toko Contoh', 'slug' => 'toko-contoh']))
            ->for($kaos)
            ->create([
                'name' => 'Kaos Polos',
                'slug' => 'kaos-polos',
                'options' => [['name' => 'Warna', 'values' => ['Hitam', 'Putih']]],
            ]);

        foreach (['Hitam' => 'KAOS-H', 'Putih' => 'KAOS-P'] as $warna => $sku) {
            ProductVariant::factory()->for($this->product)->create(['options' => ['Warna' => $warna], 'sku' => $sku]);
        }
    }

    private function url(?Product $product = null): string
    {
        $product ??= $this->product;

        return route('shop.products.show', [$product->store->slug, $product->slug]);
    }

    public function test_guests_can_see_an_active_product(): void
    {
        $this->get($this->url())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/product')
                ->where('product.name', 'Kaos Polos')
                ->where('product.options.0.name', 'Warna')
                ->has('product.variants', 2)
                ->where('store.name', 'Toko Contoh')
                ->where('store.products_count', 1)
                ->where('categoryTrail.0.name', 'Fashion')
                ->where('categoryTrail.1.name', 'Kaos'));
    }

    public function test_private_variant_fields_are_not_sent(): void
    {
        $this->get($this->url())
            ->assertInertia(fn (Assert $page) => $page
                ->missing('product.variants.0.sku')
                ->missing('product.variants.0.weight_grams'));
    }

    public function test_removed_variants_are_not_offered(): void
    {
        $this->product->variants()->where('options->Warna', 'Putih')->update(['is_active' => false]);

        $this->get($this->url())
            ->assertInertia(fn (Assert $page) => $page
                ->has('product.variants', 1)
                ->where('product.variants.0.options.Warna', 'Hitam'));
    }

    /**
     * Every hidden case answers 404, exactly like a product that never
     * existed, so the URL leaks nothing about drafts or bans.
     *
     * @return array<string, array{\Closure(Product): void}>
     */
    public static function hiddenProducts(): array
    {
        return [
            'draft' => [fn (Product $p) => $p->forceFill(['status' => ProductStatus::Draft])->save()],
            'archived' => [fn (Product $p) => $p->forceFill(['status' => ProductStatus::Archived])->save()],
            'banned' => [fn (Product $p) => $p->forceFill(['status' => ProductStatus::Banned])->save()],
            'deleted' => [fn (Product $p) => $p->delete()],
            'store pending' => [fn (Product $p) => $p->store->forceFill(['status' => StoreStatus::Pending])->save()],
            'store suspended' => [fn (Product $p) => $p->store->forceFill(['status' => StoreStatus::Suspended])->save()],
            'no variant on sale' => [fn (Product $p) => $p->variants()->update(['is_active' => false])],
        ];
    }

    /**
     * @param  \Closure(Product): void  $hide
     */
    #[DataProvider('hiddenProducts')]
    public function test_hidden_products_are_not_found(\Closure $hide): void
    {
        $url = $this->url();
        $hide($this->product);

        $this->get($url)->assertNotFound();
        // Not even its own seller gets the public page (they have the editor).
        $this->actingAs($this->product->store->user)->get($url)->assertNotFound();
    }

    public function test_a_product_slug_only_works_under_its_own_store(): void
    {
        $other = Store::factory()->active()->create(['slug' => 'toko-lain']);

        $this->get(route('shop.products.show', ['toko-lain', 'kaos-polos']))->assertNotFound();
        $this->assertTrue($other->exists);
    }

    public function test_the_store_count_only_includes_visible_products(): void
    {
        Product::factory()->for($this->product->store)->create(); // draft, no variants

        $this->get($this->url())->assertInertia(fn (Assert $page) => $page->where('store.products_count', 1));
    }
}
