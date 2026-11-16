<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Price and stock live on variants; every product has at least one.
 *
 * @property int $id
 * @property int $store_id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property list<array{name: string, values: list<string>}> $options In the seller's order; [] for a simple product.
 * @property ProductStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 *
 * Only present when loaded with withCount()/withMin()/withMax()/withSum():
 * @property-read int|null $variants_count
 * @property-read int|string|null $variants_min_price
 * @property-read int|string|null $variants_max_price
 * @property-read int|string|null $variants_sum_stock
 */
// Not fillable on purpose: store_id (set via $store->products()->create(),
// so a seller can never post into another store) and status (Banned is
// admin-only, so it's always set explicitly after validation).
#[Fillable(['category_id', 'name', 'slug', 'description', 'options'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'options' => '[]',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * The variants on sale now. Removed variants stay in the table as
     * inactive, so carts and orders that point at them never break.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    /**
     * Gallery in display order; the first image is the cover.
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'options' => 'array',
        ];
    }
}
