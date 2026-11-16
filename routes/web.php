<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Seller\ProductImageController;
use App\Http\Controllers\Seller\StoreController as SellerStoreController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// Seller area: any verified user can open (one) store.
Route::middleware(['auth', 'verified'])
    ->prefix('seller')
    ->name('seller.')
    ->group(function () {
        Route::get('store', [SellerStoreController::class, 'show'])->name('store.show');
        Route::get('store/create', [SellerStoreController::class, 'create'])->name('store.create');
        Route::post('store', [SellerStoreController::class, 'store'])->name('store.store');
        Route::put('store', [SellerStoreController::class, 'update'])->name('store.update');

        Route::resource('products', SellerProductController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);

        // scopeBindings: /products/1/images/99 only resolves if image 99
        // really belongs to product 1 (otherwise 404).
        Route::scopeBindings()->group(function () {
            Route::get('products/{product}/images', [ProductImageController::class, 'index'])->name('products.images.index');
            Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
            Route::put('products/{product}/images/order', [ProductImageController::class, 'reorder'])->name('products.images.reorder');
            Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
        });
    });

// Marketplace staff only. `can:admin` runs the Gate defined in AppServiceProvider.
Route::middleware(['auth', 'verified', 'can:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('categories', CategoryController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::get('stores', [StoreController::class, 'index'])->name('stores.index');
        Route::post('stores/{store}/approve', [StoreController::class, 'approve'])->name('stores.approve');
        Route::post('stores/{store}/suspend', [StoreController::class, 'suspend'])->name('stores.suspend');

        Route::get('products', [AdminProductController::class, 'index'])->name('products.index');
        Route::post('products/{product}/ban', [AdminProductController::class, 'ban'])->name('products.ban');
        Route::post('products/{product}/unban', [AdminProductController::class, 'unban'])->name('products.unban');
    });

// Living style guide for the UI components. Local development only: it is
// never registered in testing or production.
if (app()->isLocal()) {
    Route::inertia('dev/design-system', 'dev/design-system')->name('dev.design-system');
}

require __DIR__.'/settings.php';
