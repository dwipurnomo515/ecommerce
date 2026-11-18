<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\ModerateProduct;
use App\Enums\ProductStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('moderate', Product::class);

        // Default tab: what buyers can see now, the products worth checking.
        $status = ProductStatus::tryFrom($request->string('status')->value()) ?? ProductStatus::Active;
        $search = trim($request->string('q')->value());

        $onlyActive = fn (Builder $query) => $query->where('is_active', true);

        $products = Product::query()
            ->with(['store:id,name', 'category:id,name'])
            ->with(['images' => fn ($query) => $query->limit(1)])
            ->withMin(['variants' => $onlyActive], 'price')
            ->withMax(['variants' => $onlyActive], 'price')
            ->where('status', $status)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = Like::contains($search);
                // Product name OR store name, case-insensitive (Postgres ILIKE).
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'ilike', $pattern)
                    ->orWhereHas('store', fn (Builder $store) => $store->where('name', 'ilike', $pattern)));
            })
            ->latest($status === ProductStatus::Banned ? 'banned_at' : 'created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'cover_url' => $product->images->first()?->thumbUrl(),
                'store' => $product->store->name,
                'category' => $product->category->name,
                'min_price' => (int) $product->variants_min_price,
                'max_price' => (int) $product->variants_max_price,
                'created_at' => $product->created_at?->toIso8601String(),
                'ban_reason' => $product->ban_reason,
                'banned_at' => $product->banned_at?->toIso8601String(),
            ]);

        /** @var array<string, int> $counts */
        $counts = Product::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'status' => $status->value,
            'search' => $search,
            'tabs' => array_map(fn (ProductStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'count' => (int) ($counts[$s->value] ?? 0),
            ], ProductStatus::cases()),
        ]);
    }

    public function ban(Request $request, Product $product, ModerateProduct $moderate): RedirectResponse
    {
        Gate::authorize('moderate', Product::class);

        /** @var array{reason: string} $data */
        $data = $request->validate(
            // The seller reads this to know what to fix: make it a sentence.
            ['reason' => ['required', 'string', 'min:10', 'max:500']],
            attributes: ['reason' => 'alasan'],
        );

        try {
            $moderate->ban($product, $data['reason']);
            Inertia::flash('toast', ['type' => 'success', 'message' => "Produk \"{$product->name}\" diblokir."]);
        } catch (InvalidStatusTransition) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Produk \"{$product->name}\" sudah diblokir."]);
        }

        return back();
    }

    public function unban(Product $product, ModerateProduct $moderate): RedirectResponse
    {
        Gate::authorize('moderate', Product::class);

        try {
            $moderate->unban($product);
            Inertia::flash('toast', ['type' => 'success', 'message' => "Blokir \"{$product->name}\" dibuka. Produk kembali menjadi draf penjual."]);
        } catch (InvalidStatusTransition) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Produk \"{$product->name}\" sudah tidak diblokir."]);
        }

        return back();
    }
}
