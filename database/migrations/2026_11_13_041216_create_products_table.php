<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A product listed by a store. Price and stock live on its variants:
     * every product has at least one variant (a "simple" product has one).
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Stores get suspended, never deleted, so RESTRICT is a safety net.
            $table->foreignId('store_id')->constrained()->restrictOnDelete();

            // A category in use can't be deleted; move its products first.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            $table->string('name', 200);
            $table->string('slug', 220);
            $table->text('description')->nullable();

            // draft | active | archived (by seller) | banned (by admin)
            $table->string('status', 20)->default('draft');

            $table->timestamps();

            // Adds a nullable deleted_at column. "Deleting" only fills it in,
            // so past orders that point at this product never break.
            $table->softDeletes();

            // URL is /{store-slug}/{product-slug}: unique per store, not globally.
            // Also serves as the index for "all products of store X".
            $table->unique(['store_id', 'slug']);

            // Storefront query: active products in a category.
            $table->index(['category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
