<?php

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProduct
{
    /**
     * @param  array{
     *     name: string,
     *     category_id: int,
     *     description?: string|null,
     *     status: string,
     *     variants: list<array<string, mixed>>
     * }  $data  Already validated by ProductRequest.
     */
    public function handle(Store $store, array $data): Product
    {
        // A product and its variants are saved together or not at all. If
        // variant #3 fails to insert, product + variants #1-2 are rolled back
        // instead of leaving a half-created product behind.
        return DB::transaction(function () use ($store, $data): Product {
            $product = $store->products()->make([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($store, $data['name']),
                'description' => $data['description'] ?? null,
            ]);
            $product->status = ProductStatus::from($data['status']);
            $product->save();

            $product->variants()->createMany(array_map(fn (array $variant) => [
                'options' => $variant['options'],
                'sku' => ($variant['sku'] ?? '') !== '' ? $variant['sku'] : null,
                'price' => $variant['price'],
                'compare_at_price' => $variant['compare_at_price'] ?? null,
                'stock' => $variant['stock'],
                'weight_grams' => $variant['weight_grams'],
            ], $data['variants']));

            return $product;
        });
    }

    /**
     * "Kaos Polos" → "kaos-polos", or "kaos-polos-2" if the store already has
     * one. Soft-deleted products still hold their slug (the unique index
     * covers them), so they are checked too.
     */
    private function uniqueSlug(Store $store, string $name): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $n = 2;

        while ($store->products()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
