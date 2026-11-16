<?php

use App\Http\Controllers\Admin\CategoryController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// Marketplace staff only. `can:admin` runs the Gate defined in AppServiceProvider.
Route::middleware(['auth', 'verified', 'can:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('categories', CategoryController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });

// Living style guide for the UI components. Local development only: it is
// never registered in testing or production.
if (app()->isLocal()) {
    Route::inertia('dev/design-system', 'dev/design-system')->name('dev.design-system');
}

require __DIR__.'/settings.php';
