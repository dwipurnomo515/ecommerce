<?php

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Saves the edit form. Variants are matched by their option combination
 * ("upsert"), never deleted and re-created:
 *
 *   in form + in DB      → update price/stock/... (same id, same row)
 *   in form only         → insert, or re-activate a previously removed one
 *   in DB only           → deactivate (is_active = false), keep the row
 *
 * Keeping ids stable matters from Tahap 4 on: cart items and orders point
 * at a variant id. Delete-and-recreate would break them on every edit.
 */
class UpdateProduct
{
    /**
     * @param  array{
     *     name: string,
     *     category_id: int,
     *     description?: string|null,
     *     status: string,
     *     options: list<array{name: string, values: list<string>}>,
     *     variants: list<array<string, mixed>>
     * }  $data  Already validated by ProductRequest.
     */
    public function handle(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            // The policy checked "not banned" when the request came in, but an
            // admin may have banned it a moment later (time-of-check vs
            // time-of-use). Re-check on the locked row, or this save would
            // overwrite the ban with the seller's status. ModerateProduct
            // takes the same lock, so the two can't interleave.
            // Eloquent's value() returns the CAST attribute: an enum, not 'banned'.
            $current = Product::query()->lockForUpdate()->whereKey($product->id)->value('status');

            if ($current === ProductStatus::Banned) {
                throw new AuthorizationException('Produk ini diblokir admin.');
            }

            // The slug is deliberately NOT regenerated from the new name:
            // links that buyers shared or Google indexed must keep working.
            $product->fill([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'options' => $data['options'],
            ]);
            $product->status = ProductStatus::from($data['status']);
            $product->save();

            $this->releaseSkus($product, $data['variants']);

            // Loaded AFTER releaseSkus(), so each model's "original" sku is
            // what the database holds now. Loading it before would make
            // Eloquent think an unchanged sku is not dirty and skip saving
            // it, leaving the NULL from releaseSkus() behind.
            // lockForUpdate: two tabs saving at once queue up instead of
            // both inserting the same new combination.
            $existing = $product->variants()->lockForUpdate()->get()
                ->keyBy(fn (ProductVariant $variant) => ProductVariant::optionsKey($variant->options));

            $kept = [];

            foreach ($data['variants'] as $input) {
                /** @var array<string, string> $options */
                $options = $input['options'];
                $key = ProductVariant::optionsKey($options);

                $variant = $existing->get($key) ?? $product->variants()->make(['options' => $options]);
                $variant->fill([
                    'sku' => ($input['sku'] ?? '') !== '' ? $input['sku'] : null,
                    'price' => $input['price'],
                    'compare_at_price' => $input['compare_at_price'] ?? null,
                    'stock' => $input['stock'],
                    'weight_grams' => $input['weight_grams'],
                    'is_active' => true,
                ]);
                $variant->save();

                $kept[] = $variant->id;
            }

            $product->variants()->whereKeyNot($kept)->update(['is_active' => false]);

            return $product;
        });
    }

    /**
     * (product_id, sku) is unique in the database, checked row by row. So
     * swapping two SKUs (A→B and B→A), or giving a new variant the SKU of a
     * removed one, would fail halfway through. Clearing every SKU the form
     * is about to use first makes the order of the updates irrelevant.
     *
     * @param  list<array<string, mixed>>  $variants
     */
    private function releaseSkus(Product $product, array $variants): void
    {
        $skus = array_values(array_filter(
            array_map(fn (array $variant) => $variant['sku'] ?? null, $variants),
            fn ($sku) => is_string($sku) && $sku !== '',
        ));

        if ($skus !== []) {
            $product->variants()->whereIn('sku', $skus)->update(['sku' => null]);
        }
    }
}
