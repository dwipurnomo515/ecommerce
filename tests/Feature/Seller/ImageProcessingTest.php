<?php

namespace Tests\Feature\Seller;

use App\Media\ImageProcessor;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageProcessingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'GPS-SECRET-LAT-6.2088-LNG-106.8456';

    /**
     * A real JPEG (800×400) with a real EXIF APP1 segment: Orientation = 6
     * ("rotate 90° clockwise", what phones write for portrait shots) plus
     * a marker standing in for GPS data. Built byte by byte, so the test
     * doesn't depend on a sample photo from someone's phone.
     */
    private function phonePhoto(): string
    {
        $gd = imagecreatetruecolor(800, 400);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 13, 92, 85));
        ob_start();
        imagejpeg($gd, null, 90);
        $jpeg = (string) ob_get_clean();

        $tiff = "MM\x00\x2A\x00\x00\x00\x08"          // big-endian TIFF header, IFD0 at offset 8
            ."\x00\x01"                              // 1 entry
            ."\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00" // Orientation (SHORT) = 6
            ."\x00\x00\x00\x00"                      // no next IFD
            .self::SECRET;                           // "GPS" riding along in the metadata
        $payload = "Exif\x00\x00".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        // SOI, then our APP1, then the rest of the JPEG.
        $path = tempnam(sys_get_temp_dir(), 'jpg').'.jpg';
        file_put_contents($path, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

        return $path;
    }

    public function test_the_test_photo_really_carries_the_metadata(): void
    {
        $path = $this->phonePhoto();

        $this->assertStringContainsString(self::SECRET, (string) file_get_contents($path));
        $this->assertSame(6, @exif_read_data($path)['Orientation'] ?? null);
    }

    public function test_processing_drops_all_metadata_and_turns_the_photo_upright(): void
    {
        $images = app(ImageProcessor::class)->process($this->phonePhoto());

        foreach ($images as $bytes) {
            $this->assertStringNotContainsString(self::SECRET, $bytes);
            $this->assertStringNotContainsString('Exif', $bytes);
            $this->assertSame('WEBP', substr($bytes, 8, 4), 'Re-encoded as WebP.');
        }

        // 800×400 shot in portrait: upright it is 400×800.
        $this->assertSame([400, 800], array_slice(getimagesizefromstring($images['main']) ?: [], 0, 2));
        // Thumbnail fits 480: 240×480.
        $this->assertSame([240, 480], array_slice(getimagesizefromstring($images['thumb']) ?: [], 0, 2));
    }

    public function test_large_photos_are_scaled_down_never_up(): void
    {
        $big = UploadedFile::fake()->image('besar.jpg', 4000, 3000);
        $small = UploadedFile::fake()->image('kecil.png', 500, 500);

        $large = app(ImageProcessor::class)->process($big->getRealPath());
        $tiny = app(ImageProcessor::class)->process($small->getRealPath());

        $this->assertSame([1600, 1200], array_slice(getimagesizefromstring($large['main']) ?: [], 0, 2));
        $this->assertSame([500, 500], array_slice(getimagesizefromstring($tiny['main']) ?: [], 0, 2), 'Not enlarged.');
    }

    public function test_an_upload_never_stores_the_original(): void
    {
        Storage::fake('public');
        $store = Store::factory()->active()->create();
        $product = Product::factory()->for($store)->create();

        $upload = new UploadedFile($this->phonePhoto(), 'foto-hp.jpg', 'image/jpeg', null, true);
        $this->actingAs($store->user)
            ->post(route('seller.products.images.store', $product), ['images' => [$upload]])
            ->assertSessionHasNoErrors();

        $image = ProductImage::query()->sole();
        $this->assertStringEndsWith('.webp', $image->path);
        $this->assertStringEndsWith('-thumb.webp', (string) $image->thumb_path);

        $files = Storage::disk('public')->allFiles("products/{$product->id}");
        $this->assertCount(2, $files, 'Main + thumbnail only: no original.');
        foreach ($files as $file) {
            $this->assertStringNotContainsString(self::SECRET, (string) Storage::disk('public')->get($file));
        }

        $this->actingAs($store->user)->delete(route('seller.products.images.destroy', [$product, $image]));
        $this->assertSame([], Storage::disk('public')->allFiles("products/{$product->id}"), 'Both files deleted.');
    }

    public function test_old_photos_are_reprocessed_by_the_command(): void
    {
        Storage::fake('public');
        $product = Product::factory()->for(Store::factory()->active())->create();
        Storage::disk('public')->put("products/{$product->id}/lama.jpg", (string) file_get_contents($this->phonePhoto()));
        $image = $product->images()->create(['path' => "products/{$product->id}/lama.jpg", 'sort_order' => 0]);

        $this->artisan('images:reprocess')->expectsOutput('1 foto diproses ulang, 0 gagal.')->assertSuccessful();

        $image->refresh();
        $this->assertNotNull($image->thumb_path);
        Storage::disk('public')->assertMissing("products/{$product->id}/lama.jpg");
        $this->assertStringNotContainsString(self::SECRET, (string) Storage::disk('public')->get($image->path));

        // Running again finds nothing left to do.
        $this->artisan('images:reprocess')->expectsOutput('0 foto diproses ulang, 0 gagal.');
    }
}
