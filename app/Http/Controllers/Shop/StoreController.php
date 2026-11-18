<?php

namespace App\Http\Controllers\Shop;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Support\ProductCards;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    /**
     * /toko/{slug}: a store's public page. Pending and suspended stores
     * answer 404, like a store that never existed.
     */
    public function show(Request $request, string $storeSlug): Response
    {
        $store = Store::query()
            ->where('slug', $storeSlug)
            ->where('status', StoreStatus::Active)
            ->firstOrFail();

        $sort = ProductCards::sortFrom($request);

        return Inertia::render('shop/store', [
            'store' => [
                'name' => $store->name,
                'slug' => $store->slug,
                'description' => $store->description,
                'joined_at' => $store->approved_at?->toIso8601String(),
                'rating' => $store->ratingSummary(),
            ],
            'products' => ProductCards::query($sort)
                ->where('store_id', $store->id)
                ->paginate(24)
                ->withQueryString()
                ->through(fn (Product $product) => ProductCards::toCard($product)),
            'sort' => $sort,
        ]);
    }
}
