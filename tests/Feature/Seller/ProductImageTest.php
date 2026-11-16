<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private FilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();

        // An in-memory stand-in for the real disk: nothing touches storage/.
        $this->disk = Storage::fake('public');
        $this->product = Product::factory()->for(Store::factory()->active())->create();
    }

    private function photo(string $name = 'foto.jpg', int $size = 800): UploadedFile
    {
        return UploadedFile::fake()->image($name, $size, $size);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function upload(array $files, ?Product $product = null): TestResponse
    {
        $product ??= $this->product;

        return $this->actingAs($this->product->store->user)
            ->post(route('seller.products.images.store', $product), ['images' => $files]);
    }

    public function test_the_owner_uploads_photos_in_order_with_safe_random_names(): void
    {
        $this->upload([$this->photo('../../hack.jpg'), $this->photo('kedua.png')])
            ->assertRedirect(route('seller.products.images.index', $this->product))
            ->assertSessionHasNoErrors();

        $images = $this->product->images()->get();
        $this->assertCount(2, $images);
        $this->assertSame([1, 2], $images->pluck('sort_order')->all());

        foreach ($images as $image) {
            $this->disk->assertExists($image->path);
            $this->assertStringStartsWith("products/{$this->product->id}/", $image->path);
            $this->assertStringNotContainsString('hack', $image->path);
            $this->assertStringNotContainsString('kedua', $image->path);
        }
    }

    public function test_the_photos_page_lists_urls_cover_first(): void
    {
        $this->upload([$this->photo(), $this->photo()]);

        $this->actingAs($this->product->store->user)
            ->get(route('seller.products.images.index', $this->product))
            ->assertInertia(fn (Assert $page) => $page
                ->component('seller/products/images')
                ->has('images', 2)
                ->where('images.0.url', fn (string $url) => str_contains($url, "products/{$this->product->id}/"))
                ->where('maxImages', ProductImage::MAX_PER_PRODUCT));
    }

    public function test_other_sellers_cannot_see_or_change_the_photos(): void
    {
        $intruder = Store::factory()->active()->create()->user;

        $this->actingAs($intruder)->get(route('seller.products.images.index', $this->product))->assertForbidden();
        $this->actingAs($intruder)
            ->post(route('seller.products.images.store', $this->product), ['images' => [$this->photo()]])
            ->assertForbidden();

        $this->assertSame(0, $this->product->images()->count());
    }

    public function test_only_real_jpg_png_or_webp_images_are_accepted(): void
    {
        $cases = [
            'pdf' => UploadedFile::fake()->create('katalog.pdf', 100, 'application/pdf'),
            'svg' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'fake jpg' => UploadedFile::fake()->createWithContent('bukan-gambar.jpg', 'ini teks biasa'),
            'too small' => $this->photo('kecil.jpg', 200),
            'too big' => $this->photo('besar.jpg')->size(6 * 1024),
        ];

        foreach ($cases as $case => $file) {
            try {
                $this->upload([$file])->assertSessionHasErrors('images.0');
            } catch (ExpectationFailedException $e) {
                $this->fail("Case [{$case}] should have been rejected. ".$e->getMessage());
            }
        }

        $this->assertSame(0, $this->product->images()->count());
        $this->assertEmpty($this->disk->allFiles());
    }

    public function test_a_product_has_at_most_eight_photos(): void
    {
        ProductImage::factory()->for($this->product)->count(7)->create();

        $this->upload([$this->photo(), $this->photo()])->assertSessionHasErrors('images');
        $this->assertSame(7, $this->product->images()->count());

        $this->upload([$this->photo()])->assertSessionHasNoErrors();
        $this->assertSame(8, $this->product->images()->count());
    }

    public function test_photos_can_be_reordered_to_pick_a_new_cover(): void
    {
        [$a, $b, $c] = ProductImage::factory()->for($this->product)->count(3)
            ->sequence(fn ($s) => ['sort_order' => $s->index])->create()->all();

        $this->actingAs($this->product->store->user)
            ->put(route('seller.products.images.reorder', $this->product), ['order' => [$c->id, $a->id, $b->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$c->id, $a->id, $b->id], $this->product->images()->pluck('id')->all());
    }

    public function test_reordering_rejects_photos_of_another_product(): void
    {
        $mine = ProductImage::factory()->for($this->product)->create();
        $foreign = ProductImage::factory()->create();

        $this->actingAs($this->product->store->user)
            ->put(route('seller.products.images.reorder', $this->product), ['order' => [$foreign->id]])
            ->assertSessionHasErrors('order.0');

        $this->assertSame($mine->sort_order, $mine->fresh()?->sort_order);
    }

    public function test_deleting_a_photo_removes_the_row_and_the_file(): void
    {
        $this->upload([$this->photo()]);
        $image = $this->product->images()->sole();

        $this->actingAs($this->product->store->user)
            ->delete(route('seller.products.images.destroy', [$this->product, $image]))
            ->assertRedirect();

        $this->assertModelMissing($image);
        $this->disk->assertMissing($image->path);
    }

    public function test_a_photo_cannot_be_deleted_through_another_product_url(): void
    {
        // The seller owns $this->product but uses its URL to target a photo
        // of somebody else's product.
        $foreign = ProductImage::factory()->create();

        $this->actingAs($this->product->store->user)
            ->delete(route('seller.products.images.destroy', [$this->product, $foreign]))
            ->assertNotFound();

        $this->assertModelExists($foreign);
    }
}
