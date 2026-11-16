<?php

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Admin moderation: ban a product (with a reason the seller will see) and
 * lift the ban.
 */
class ModerateProduct
{
    /**
     * @throws InvalidStatusTransition when it is already banned
     */
    public function ban(Product $product, string $reason): Product
    {
        return DB::transaction(function () use ($product, $reason): Product {
            // Locked re-read: two admins clicking at once, or a seller saving
            // the edit form at the same moment, wait for each other.
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($locked->status === ProductStatus::Banned) {
                throw InvalidStatusTransition::from($locked->status, ProductStatus::Banned);
            }

            $locked->status = ProductStatus::Banned;
            $locked->ban_reason = $reason;
            $locked->banned_at = now();
            $locked->save();

            return $locked;
        });
    }

    /**
     * Back to DRAFT, not to whatever it was before: the seller should check
     * (and fix) the product before it goes on sale again.
     *
     * @throws InvalidStatusTransition when it is not banned
     */
    public function unban(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($locked->status !== ProductStatus::Banned) {
                throw InvalidStatusTransition::from($locked->status, ProductStatus::Draft);
            }

            $locked->status = ProductStatus::Draft;
            $locked->ban_reason = null;
            $locked->banned_at = null;
            $locked->save();

            return $locked;
        });
    }
}
