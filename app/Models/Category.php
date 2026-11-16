<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Global category tree (max 3 levels), managed by admins.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['parent_id', 'name', 'slug', 'description', 'is_active', 'sort_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const MAX_DEPTH = 3;

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * IDs from the direct parent up to the root. One small query per level,
     * which is fine because the tree is at most MAX_DEPTH levels deep.
     *
     * @return list<int>
     */
    public function ancestorIds(): array
    {
        $ids = [];
        $parentId = $this->parent_id;

        while ($parentId !== null && count($ids) <= self::MAX_DEPTH) {
            $ids[] = $parentId;
            $parentId = self::query()->whereKey($parentId)->value('parent_id');
        }

        return $ids;
    }

    /**
     * This category plus every ACTIVE category below it, so the "Fashion"
     * page also lists products filed under "Fashion › Pria › Kaos".
     * One query per level (at most MAX_DEPTH).
     *
     * @return list<int>
     */
    public function selfAndActiveDescendantIds(): array
    {
        $ids = [$this->id];
        $level = [$this->id];

        for ($depth = 1; $depth < self::MAX_DEPTH && $level !== []; $depth++) {
            /** @var list<int> $level */
            $level = self::query()->whereIn('parent_id', $level)->where('is_active', true)->pluck('id')->all();
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    /**
     * Sellers may only use a category that is active AND whose parents are
     * all active (deactivating "Fashion" hides "Fashion > Pria" too).
     */
    public function isSelectable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $ancestors = $this->ancestorIds();

        return $ancestors === []
            || self::query()->whereKey($ancestors)->where('is_active', false)->doesntExist();
    }

    /**
     * 1 for a root category, 2 for its children, 3 for grandchildren.
     */
    public function level(): int
    {
        return count($this->ancestorIds()) + 1;
    }

    /**
     * How many levels exist BELOW this category: 0 for a leaf,
     * 1 if it has children, 2 if it also has grandchildren.
     */
    public function descendantDepth(): int
    {
        $depth = 0;
        $ids = [$this->id];

        while ($depth < self::MAX_DEPTH) {
            $ids = self::query()->whereIn('parent_id', $ids)->pluck('id')->all();

            if ($ids === []) {
                break;
            }

            $depth++;
        }

        return $depth;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
