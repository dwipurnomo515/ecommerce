<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ProductImageUploadRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductImageController extends Controller
{
    public function index(Product $product): Response
    {
        Gate::authorize('update', $product);

        return Inertia::render('seller/products/images', [
            'product' => ['id' => $product->id, 'name' => $product->name],
            'images' => $product->images->map(fn (ProductImage $image) => [
                'id' => $image->id,
                'url' => $image->url(),
            ]),
            'maxImages' => ProductImage::MAX_PER_PRODUCT,
        ]);
    }

    public function store(ProductImageUploadRequest $request, Product $product): RedirectResponse
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('images');
        $nextOrder = (int) $product->images()->max('sort_order') + 1;

        foreach ($files as $file) {
            // store() picks a RANDOM name (hashName) and the extension from
            // the file content. The name the user's computer gave the file
            // is never used: it could be "../../.env" or "<script>.jpg".
            $path = $file->store("products/{$product->id}", ['disk' => config()->string('filesystems.media_disk')]);

            $product->images()->create(['path' => $path, 'sort_order' => $nextOrder++]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => count($files).' foto ditambahkan.']);

        return to_route('seller.products.images.index', $product);
    }

    /**
     * Receives the image ids in the new display order. The first is the cover.
     */
    public function reorder(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $ids = $product->images()->pluck('id')->all();

        /** @var array{order: list<int>} $data */
        $data = $request->validate([
            // Exactly this product's images, each once: no foreign ids.
            'order' => ['required', 'array', 'size:'.count($ids)],
            'order.*' => ['integer', 'distinct', Rule::in($ids)],
        ]);

        DB::transaction(function () use ($product, $data): void {
            foreach ($data['order'] as $position => $id) {
                $product->images()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return to_route('seller.products.images.index', $product);
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        Gate::authorize('update', $product);

        $image->delete();
        // Remove the file only after the row is gone. If this fails, the
        // leftover file is harmless; a row pointing to a missing file is not.
        ProductImage::disk()->delete($image->path);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto dihapus.']);

        return to_route('seller.products.images.index', $product);
    }
}
