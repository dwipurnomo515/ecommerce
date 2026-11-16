<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProductImageFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $product_id
 * @property string $path Path on the storage disk, not a URL.
 * @property string|null $alt
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['path', 'alt', 'sort_order'])]
class ProductImage extends Model
{
    /** Gallery size per product. */
    public const MAX_PER_PRODUCT = 8;

    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Public URL, built from the media disk so it works the same for local
     * storage (/storage/...) and S3 later.
     */
    public function url(): string
    {
        return self::disk()->url($this->path);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(config()->string('filesystems.media_disk'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
