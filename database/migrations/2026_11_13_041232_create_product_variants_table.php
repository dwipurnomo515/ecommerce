<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sellable unit: what goes into a cart and an order.
     * e.g. "Kaos Polos" -> variants {Warna: Hitam, Ukuran: M}, {Warna: Hitam, Ukuran: L}
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            // Products are soft-deleted, so CASCADE only fires on a permanent
            // delete (e.g. a draft that was never sold). Order items will later
            // RESTRICT deleting a variant that has been sold.
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Seller's own stock code. Two sellers may both use "KAOS-001",
            // so it's only unique within a product. NULLs are allowed (optional).
            $table->string('sku', 64)->nullable();

            // {"Warna": "Hitam", "Ukuran": "XL"}; {} for a simple product.
            $table->jsonb('options')->default('{}');

            // Money as whole Rupiah in an integer: never float (0.1 + 0.2 != 0.3).
            $table->bigInteger('price');
            // Strike-through "was" price; NULL = not on sale.
            $table->bigInteger('compare_at_price')->nullable();

            $table->integer('stock')->default(0);
            // Couriers price shipping by weight.
            $table->integer('weight_grams');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Also serves as the index for "variants of product X".
            $table->unique(['product_id', 'sku']);
        });

        // PostgreSQL ignores "unsigned", so business rules on numbers are
        // enforced with CHECK constraints. stock >= 0 is what will make
        // overselling impossible at checkout (Tahap 4), even under a race.
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_compare_at_price_check CHECK (compare_at_price IS NULL OR compare_at_price > price)');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_stock_check CHECK (stock >= 0)');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_weight_grams_check CHECK (weight_grams > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
