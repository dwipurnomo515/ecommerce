<?php

namespace App\Models;

use App\Media\ImageProcessor;
use Carbon\CarbonImmutable;
use Database\Factories\ProductImageFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $product_id
 * @property string $path Path on the storage disk, not a URL.
 * @property string|null $thumb_path Small version; null until reprocessed.
 * @property string|null $alt
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['path', 'thumb_path', 'alt', 'sort_order'])]
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

    /** For grids, cart, checkout. Falls back to the main image. */
    public function thumbUrl(): string
    {
        return self::disk()->url($this->thumb_path ?? $this->path);
    }

    /**
     * Re-encodes a photo (EXIF/GPS gone, upright, two WebP sizes) and
     * writes both under random names. The original file is never stored.
     *
     * @return array{path: string, thumb_path: string}
     */
    public static function storeProcessed(string $sourcePath, int $productId): array
    {
        $images = app(ImageProcessor::class)->process($sourcePath);
        $name = Str::random(40);

        $paths = [
            'path' => "products/{$productId}/{$name}.webp",
            'thumb_path' => "products/{$productId}/{$name}-thumb.webp",
        ];

        self::disk()->put($paths['path'], $images['main']);
        self::disk()->put($paths['thumb_path'], $images['thumb']);

        return $paths;
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
