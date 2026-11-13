<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_store_is_pending_until_an_admin_approves_it(): void
    {
        $user = User::factory()->create();

        $store = $user->store()->create(['name' => 'Toko Budi', 'slug' => 'toko-budi']);

        $this->assertSame(StoreStatus::Pending, $store->status);
        $this->assertFalse($store->isActive());
        $this->assertTrue($user->fresh()?->store?->is($store));
    }

    public function test_a_seller_cannot_approve_their_own_store_through_form_input(): void
    {
        $user = User::factory()->create();

        $this->expectException(MassAssignmentException::class);

        $user->store()->create([
            'name' => 'Toko Curang',
            'slug' => 'toko-curang',
            'status' => 'active',
        ]);
    }

    public function test_a_product_cannot_be_moved_into_another_store_through_form_input(): void
    {
        $product = Product::factory()->create();
        $otherStore = Store::factory()->create();

        $this->expectException(MassAssignmentException::class);

        $product->update(['store_id' => $otherStore->id]);
    }

    public function test_product_relationships_and_casts(): void
    {
        $product = Product::factory()
            ->active()
            ->has(ProductVariant::factory()->state([
                'options' => ['Warna' => 'Hitam', 'Ukuran' => 'XL'],
                'price' => 150_000,
            ]), 'variants')
            ->create();

        $product->load('store', 'category', 'variants');
        $variant = $product->variants->first();

        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertTrue($product->store->isActive());
        $this->assertNotNull($variant);
        $this->assertSame(['Warna' => 'Hitam', 'Ukuran' => 'XL'], $variant->options);
        $this->assertSame(150_000, $variant->price);
        $this->assertFalse($variant->isOnSale());
    }

    public function test_gallery_images_come_back_in_display_order(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->create(['path' => 'b.jpg', 'sort_order' => 2]);
        ProductImage::factory()->for($product)->create(['path' => 'a.jpg', 'sort_order' => 1]);

        $this->assertSame(['a.jpg', 'b.jpg'], $product->images()->pluck('path')->all());
    }

    public function test_deleting_a_product_only_hides_it_and_keeps_its_variants(): void
    {
        $product = Product::factory()->has(ProductVariant::factory(), 'variants')->create();

        $product->delete();

        $this->assertNull(Product::find($product->id));
        $this->assertNotNull(Product::withTrashed()->find($product->id));
        $this->assertSame(1, ProductVariant::where('product_id', $product->id)->count());
    }

    public function test_categories_form_a_tree(): void
    {
        $fashion = Category::factory()->create(['name' => 'Fashion', 'slug' => 'fashion']);
        $kaos = Category::factory()->childOf($fashion)->create(['sort_order' => 2]);
        $kemeja = Category::factory()->childOf($fashion)->create(['sort_order' => 1]);

        $this->assertTrue($kaos->parent?->is($fashion));
        $this->assertSame([$kemeja->id, $kaos->id], $fashion->children()->pluck('id')->all());
    }
}
