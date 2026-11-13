<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product gallery. The image with the lowest sort_order is the cover.
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Path on the storage disk (local now, S3-compatible in production),
            // never a full URL, so the storage location can change later.
            $table->string('path');

            // Alt text: accessibility (screen readers) and image SEO.
            $table->string('alt', 200)->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // Gallery query: images of product X in display order.
            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
