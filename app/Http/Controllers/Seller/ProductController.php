<?php

namespace App\Http\Controllers\Seller;

use App\Actions\Products\CreateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $store = $this->user($request)->store;

        if ($store === null) {
            return to_route('seller.store.create');
        }

        $products = $store->products()
            ->with('category:id,name')
            // Only the cover per product. Laravel turns limit() inside an
            // eager load into a per-product window query: still one query.
            ->with(['images' => fn ($query) => $query->limit(1)])
            ->withCount('variants')
            ->withMin('variants', 'price')
            ->withMax('variants', 'price')
            ->withSum('variants', 'stock')
            ->latest()
            ->paginate(20)
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'cover_url' => $product->images->first()?->url(),
                'category' => $product->category->name,
                'status' => $product->status->value,
                'status_label' => $product->status->label(),
                'variants_count' => $product->variants_count,
                'min_price' => (int) $product->variants_min_price,
                'max_price' => (int) $product->variants_max_price,
                'total_stock' => (int) $product->variants_sum_stock,
            ]);

        return Inertia::render('seller/products/index', [
            'products' => $products,
            'canCreate' => $store->isActive(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('seller/products/create', [
            'categories' => Category::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'parent_id', 'name', 'is_active']),
            'limits' => [
                'options' => ProductRequest::MAX_OPTIONS,
                'valuesPerOption' => ProductRequest::MAX_VALUES_PER_OPTION,
                'variants' => ProductRequest::MAX_VARIANTS,
            ],
        ]);
    }

    public function store(ProductRequest $request, CreateProduct $createProduct): RedirectResponse
    {
        /** @var Store $store ProductRequest::authorize() guarantees an active store */
        $store = $this->user($request)->store;

        /** @phpstan-ignore argument.type (shape is guaranteed by ProductRequest) */
        $product = $createProduct->handle($store, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Produk \"{$product->name}\" disimpan. Sekarang tambahkan fotonya."]);

        // Next natural step for the seller: photos.
        return to_route('seller.products.images.index', $product);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
