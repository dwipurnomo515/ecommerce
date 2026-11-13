<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
