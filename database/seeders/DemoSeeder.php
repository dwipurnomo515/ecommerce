<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\User;
use App\Shipping\FlatRateShipping;
use Database\Seeders\Support\DemoArtwork;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Sample data to click through the whole shop locally:
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Everything is labelled "Contoh". Refuses to run outside local: demo
 * accounts with a known password must never exist on a real server.
 * Safe to run again: existing demo rows are reused, not duplicated.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('DemoSeeder only runs in the local environment.');
        }

        $admin = $this->user('admin@example.com', 'Admin Contoh');
        $admin->is_admin = true;
        $admin->save();

        $seller = $this->user('penjual@example.com', 'Penjual Contoh');
        $store = $seller->store ?? $seller->store()->create([
            'name' => 'Toko Contoh',
            'slug' => 'toko-contoh',
            'description' => 'Toko berisi data contoh untuk mencoba Zetago.',
        ]);
        $store->status = StoreStatus::Active;
        $store->approved_at ??= now();
        if ($store->origin_destination_id === null) {
            // Only when empty: never overwrite a real region chosen in the app.
            $store->setShippingOrigin(FlatRateShipping::sample(1)); // Bandung
        }
        $store->save();

        $category = Category::query()->firstOrCreate(
            ['slug' => 'fashion-contoh'],
            ['name' => 'Fashion (Contoh)', 'is_active' => true],
        );

        $kitchen = Category::query()->firstOrCreate(
            ['slug' => 'dapur-contoh'],
            ['name' => 'Dapur (Contoh)', 'is_active' => true],
        );

        $this->product($store, $category, 'Kaos Polos (Contoh)', 'kaos-polos-contoh', 'tee', 250,
            [['name' => 'Ukuran', 'values' => ['M', 'L']]],
            [[['Ukuran' => 'M'], 75_000, 20], [['Ukuran' => 'L'], 80_000, 20]]);

        $this->product($store, $category, 'Topi Rimba (Contoh)', 'topi-rimba-contoh', 'hat', 150, [],
            [[[], 45_000, 10]]);

        $this->product($store, $category, 'Tas Tote Kanvas (Contoh)', 'tas-tote-kanvas-contoh', 'tote', 300, [],
            [[[], 120_000, 15]]);

        $this->product($store, $kitchen, 'Mug Keramik Pinus (Contoh)', 'mug-keramik-pinus-contoh', 'mug', 450, [],
            [[[], 65_000, 25]]);

        $this->product($store, $kitchen, 'Botol Minum Baja 500 ml (Contoh)', 'botol-minum-baja-contoh', 'bottle', 350, [],
            [[[], 135_000, 12]]);

        $buyer = $this->user('pembeli@example.com', 'Pembeli Contoh');

        if ($buyer->addresses()->doesntExist()) {
            $address = $buyer->addresses()->make([
                'label' => 'Rumah (Contoh)',
                'recipient_name' => 'Pembeli Contoh',
                'phone' => '081200000000',
                'street' => 'Jl. Contoh No. 1, RT 01/RW 01',
            ]);
            $region = FlatRateShipping::sample(0); // Jakarta Selatan
            $address->fill([
                'destination_id' => $region->id,
                'province' => $region->province,
                'city' => $region->city,
                'district' => $region->district,
                'subdistrict' => $region->subdistrict,
                'postal_code' => $region->postalCode,
            ]);
            $address->is_primary = true;
            $address->save();
        }

        $this->command->info('Data contoh siap. Password semua akun: '.self::PASSWORD);
        $this->command->table(['Peran', 'Email'], [
            ['Pembeli', 'pembeli@example.com'],
            ['Penjual', 'penjual@example.com'],
            ['Admin', 'admin@example.com'],
        ]);
    }

    private function user(string $email, string $name): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = Hash::make(self::PASSWORD);
        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }

    /**
     * @param  list<array{name: string, values: list<string>}>  $options
     * @param  list<array{0: array<string, string>, 1: int, 2: int}>  $variants  [options, price, stock]
     */
    private function product(Store $store, Category $category, string $name, string $slug, string $artwork, int $weightGrams, array $options, array $variants): void
    {
        $existing = $store->products()->where('slug', $slug)->first();

        if ($existing !== null) {
            // Products seeded before the artwork existed get their photos now.
            $this->photos($existing, $artwork);

            return;
        }

        $product = $store->products()->make([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'description' => 'Produk contoh untuk mencoba alur belanja. Bukan barang sungguhan.',
            'options' => $options,
        ]);
        $product->status = ProductStatus::Active;
        $product->save();

        foreach ($variants as [$variantOptions, $price, $stock]) {
            $product->variants()->create([
                'options' => $variantOptions,
                'price' => $price,
                'stock' => $stock,
                'weight_grams' => $weightGrams,
            ]);
        }

        $this->photos($product, $artwork);
    }

    /**
     * Drawn illustrations (see DemoArtwork), stored through the same
     * pipeline as a seller's upload. Skipped when the product has photos.
     */
    private function photos(Product $product, string $artwork): void
    {
        if ($product->images()->exists()) {
            return;
        }

        $shots = DemoArtwork::shots($artwork);

        try {
            foreach ($shots as $order => $path) {
                $product->images()->create([
                    ...ProductImage::storeProcessed($path, $product->id),
                    'alt' => $product->name.($order === 0 ? '' : ', detail'),
                    'sort_order' => $order,
                ]);
            }
        } finally {
            array_map(unlink(...), $shots);
        }
    }
}
