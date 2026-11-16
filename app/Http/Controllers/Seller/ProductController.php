<?php

namespace App\Http\Controllers\Seller;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\UpdateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

        $onlyActive = fn (Builder $query) => $query->where('is_active', true);

        $products = $store->products()
            ->with('category:id,name')
            // Only the cover per product. Laravel turns limit() inside an
            // eager load into a per-product window query: still one query.
            ->with(['images' => fn ($query) => $query->limit(1)])
            // Aggregates over what is on sale now, not removed variants.
            ->withCount(['variants' => $onlyActive])
            ->withMin(['variants' => $onlyActive], 'price')
            ->withMax(['variants' => $onlyActive], 'price')
            ->withSum(['variants' => $onlyActive], 'stock')
            ->latest()
            ->paginate(20)
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'cover_url' => $product->images->first()?->url(),
                'category' => $product->category->name,
                'status' => $product->status->value,
                'status_label' => $product->status->label(),
                'ban_reason' => $product->ban_reason,
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

        return Inertia::render('seller/products/create', $this->formProps());
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

    public function edit(Product $product): Response
    {
        Gate::authorize('update', $product);

        return Inertia::render('seller/products/edit', [
            ...$this->formProps(),
            // Only when buyers can actually open it; otherwise the link
            // would lead the seller to a 404 (draft, store under review...).
            'publicUrl' => Product::query()->visibleToBuyers()->whereKey($product->id)->exists()
                ? route('shop.products.show', [$product->store->slug, $product->slug], absolute: false)
                : null,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'description' => $product->description,
                'status' => $product->status->value,
                'options' => $product->options,
                'variants' => $product->activeVariants()->orderBy('id')->get()
                    ->map(fn (ProductVariant $variant) => [
                        // (object): an empty PHP array would become JSON []
                        // instead of {} for a simple product.
                        'options' => (object) $variant->options,
                        'sku' => $variant->sku,
                        'price' => $variant->price,
                        'compare_at_price' => $variant->compare_at_price,
                        'stock' => $variant->stock,
                        'weight_grams' => $variant->weight_grams,
                    ]),
            ],
        ]);
    }

    public function update(ProductRequest $request, Product $product, UpdateProduct $updateProduct): RedirectResponse
    {
        /** @phpstan-ignore argument.type (shape is guaranteed by ProductRequest) */
        $updateProduct->handle($product, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan disimpan.']);

        return to_route('seller.products.edit', $product);
    }

    /**
     * Shared by the create and edit pages.
     *
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'categories' => Category::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'parent_id', 'name', 'is_active']),
            'limits' => [
                'options' => ProductRequest::MAX_OPTIONS,
                'valuesPerOption' => ProductRequest::MAX_VALUES_PER_OPTION,
                'variants' => ProductRequest::MAX_VARIANTS,
            ],
        ];
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
