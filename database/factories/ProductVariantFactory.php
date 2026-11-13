<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'options' => [],
            // Realistic Rupiah prices: Rp 10.000 - Rp 2.000.000 in steps of 1.000
            'price' => fake()->numberBetween(10, 2000) * 1000,
            'compare_at_price' => null,
            'stock' => fake()->numberBetween(1, 100),
            'weight_grams' => fake()->numberBetween(100, 5000),
            'is_active' => true,
        ];
    }

    /**
     * Strike-through price 20% above the selling price.
     */
    public function onSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'compare_at_price' => (int) ($attributes['price'] * 1.2),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }
}
