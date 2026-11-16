<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (buyer, variant). The cart lives in the database, so it
     * is the same on the buyer's phone and laptop.
     *
     * Price is NOT stored here: the cart always shows today's price, and the
     * price is frozen only when an order is placed (Tahap 4, checkout).
     */
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Variants are deactivated, not deleted; CASCADE only fires if a
            // never-sold product is removed for good.
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->timestamps();

            // Adding the same variant twice raises the quantity instead of
            // creating a second row. Also the index for "my cart".
            $table->unique(['user_id', 'product_variant_id']);
        });

        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_check CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
