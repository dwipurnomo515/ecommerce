<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The sellable unit (what goes into a cart). Money is whole Rupiah.
 *
 * @property int $id
 * @property int $product_id
 * @property string|null $sku
 * @property array<string, string> $options e.g. ['Warna' => 'Hitam', 'Ukuran' => 'XL']
 * @property int $price
 * @property int|null $compare_at_price
 * @property int $stock
 * @property int $weight_grams
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['sku', 'options', 'price', 'compare_at_price', 'stock', 'weight_grams', 'is_active'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'options' => '{}',
        'stock' => 0,
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * A variant's identity within its product: its option combination,
     * independent of key order. ['Ukuran' => 'M', 'Warna' => 'Hitam'] and
     * ['Warna' => 'Hitam', 'Ukuran' => 'M'] give the same key.
     *
     * @param  array<string, mixed>  $options
     */
    public static function optionsKey(array $options): string
    {
        ksort($options);

        return (string) json_encode($options, JSON_UNESCAPED_UNICODE);
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null;
    }

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'stock' => 'integer',
            'weight_grams' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
