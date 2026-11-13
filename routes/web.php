<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// Living style guide for the UI components. Local development only: it is
// never registered in testing or production.
if (app()->isLocal()) {
    Route::inertia('dev/design-system', 'dev/design-system')->name('dev.design-system');
}

require __DIR__.'/settings.php';
