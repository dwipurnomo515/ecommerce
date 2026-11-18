<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            // UNIQUE: one review per bought line. Only real buyers can
            // review, and one purchase is one voice (no rating spam).
            $table->foreignId('order_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->smallInteger('rating');
            $table->text('body')->nullable();
            // Copied from the order item, e.g. "Hitam / L".
            $table->string('variant_label', 100)->nullable();
            // Filled in the next step (seller reply).
            $table->text('seller_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']); // product page, newest first
            $table->index(['store_id', 'created_at']);   // seller's review list
        });

        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5)');

        // Running totals, so a grid of 24 products shows "★ 4,7 (12)"
        // without averaging every review on every page view.
        // average = rating_sum / rating_count.
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('rating_sum')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['rating_count', 'rating_sum']);
        });

        Schema::dropIfExists('reviews');
    }
};
