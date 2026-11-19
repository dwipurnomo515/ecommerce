<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Shipping\Destination;
use App\Shipping\FlatRateShipping;
use App\Shipping\RajaOngkir;
use App\Shipping\ShippingProvider;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_are_never_created_outside_local(): void
    {
        $this->expectException(RuntimeException::class);

        $this->seed(DemoSeeder::class);
    }

    public function test_demo_products_get_processed_photos_once_and_keep_a_chosen_origin(): void
    {
        Storage::fake(config()->string('filesystems.media_disk'));
        $this->app['env'] = 'local';

        $this->seed(DemoSeeder::class);

        $products = Product::query()->with('images')->get();
        $this->assertCount(5, $products);

        foreach ($products as $product) {
            $this->assertStringContainsString('(Contoh)', $product->name);
            $this->assertCount(2, $product->images, $product->name);

            foreach ($product->images as $image) {
                ProductImage::disk()->assertExists([$image->path, $image->thumb_path]);
                $this->assertStringStartsWith('RIFF', (string) ProductImage::disk()->get($image->path)); // WebP
            }
        }

        // The seller picked a real region in the app; seeding again keeps it.
        $store = Store::query()->sole();
        $store->setShippingOrigin(new Destination(31555, 'BATUNUNGGAL, BANDUNG', 'JAWA BARAT', 'BANDUNG', 'BANDUNG KIDUL', 'BATUNUNGGAL', '40266'));
        $store->save();

        $this->seed(DemoSeeder::class);

        $this->assertSame(10, ProductImage::query()->count());
        $this->assertSame(31555, $store->fresh()?->origin_destination_id);
    }

    public function test_outside_local_the_real_shipping_client_is_used_even_without_a_key(): void
    {
        config(['services.rajaongkir.key' => null]);
        $this->app->forgetInstance(ShippingProvider::class);

        $this->assertInstanceOf(RajaOngkir::class, app(ShippingProvider::class));
    }

    public function test_the_sample_courier_is_labelled_and_charges_by_weight(): void
    {
        $shipping = new FlatRateShipping;

        $this->assertStringContainsString('(Contoh)', $shipping->searchDestinations('bandung')[0]->label);
        $this->assertSame(13_000, $shipping->quote(1, 2, 1500)[0]->cost); // 9.000 + 2 kg × 2.000
        $this->assertSame([], $shipping->searchDestinations('tidak ada'));
    }
}
