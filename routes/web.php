<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Seller\BalanceController;
use App\Http\Controllers\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Seller\ProductImageController;
use App\Http\Controllers\Seller\ReviewController as SellerReviewController;
use App\Http\Controllers\Seller\StoreController as SellerStoreController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\DestinationController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\ProductController as ShopProductController;
use App\Http\Controllers\Shop\ReviewController;
use App\Http\Controllers\Shop\StoreController as ShopStoreController;
use App\Http\Controllers\Webhooks\MidtransWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('kategori/{slug}', [CatalogController::class, 'category'])->name('shop.categories.show');
Route::get('cari', [CatalogController::class, 'search'])->name('shop.search');

// Storefront, public. Product slugs are unique per store, so the URL needs both.
Route::get('toko/{storeSlug}', [ShopStoreController::class, 'show'])->name('shop.stores.show');
Route::get('toko/{storeSlug}/{productSlug}', [ShopProductController::class, 'show'])->name('shop.products.show');

// Server-to-server notifications. No login, no CSRF (see bootstrap/app.php):
// the signature inside the payload is the proof of origin.
Route::post('webhooks/midtrans', MidtransWebhookController::class)->name('webhooks.midtrans');

// Buying needs an account (the cart is stored per user).
Route::middleware('auth')->group(function () {
    Route::get('toko/{storeSlug}/{productSlug}/beli', [CartController::class, 'loginToBuy'])->name('shop.products.login-to-buy');
    // Address autocomplete. Logged-in only and rate limited: every miss
    // in our cache spends paid RajaOngkir quota.
    Route::get('wilayah', DestinationController::class)
        ->middleware('throttle:30,1')
        ->name('destinations.search');

    Route::get('keranjang', [CartController::class, 'index'])->name('cart.index');
    Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    // Placing orders sends money-related requests: verified accounts only,
    // and a few per minute is plenty for a human.
    Route::post('checkout', [CheckoutController::class, 'store'])
        ->middleware(['verified', 'throttle:10,1'])
        ->name('checkout.store');
    Route::get('checkout/{code}', [CheckoutController::class, 'placed'])->name('checkout.placed');
    Route::post('checkout/{code}/bayar', [CheckoutController::class, 'pay'])
        ->middleware('throttle:10,1')
        ->name('checkout.pay');
    Route::get('pesanan', [OrderController::class, 'index'])->name('orders.index');
    Route::post('pesanan/{order}/diterima', [OrderController::class, 'confirm'])->name('orders.confirm');
    Route::post('ulasan/{item}', [ReviewController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('reviews.store');
    Route::post('keranjang', [CartController::class, 'store'])->name('cart.store');
    Route::patch('keranjang/{item}', [CartController::class, 'update'])->whereNumber('item')->name('cart.update');
    Route::delete('keranjang/{item}', [CartController::class, 'destroy'])->whereNumber('item')->name('cart.destroy');
});

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

        Route::get('orders', [SellerOrderController::class, 'index'])->name('orders.index');
        Route::post('orders/{order}/process', [SellerOrderController::class, 'process'])->name('orders.process');
        Route::post('orders/{order}/ship', [SellerOrderController::class, 'ship'])->name('orders.ship');

        Route::get('balance', [BalanceController::class, 'index'])->name('balance.index');

        Route::get('reviews', [SellerReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{review}/reply', [SellerReviewController::class, 'reply'])->name('reviews.reply');

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
