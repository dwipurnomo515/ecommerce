<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * checkouts: one purchase = ONE payment, possibly from several stores.
     * orders: one per store (each store ships and gets paid separately).
     * order_items: what was bought, with name and price FROZEN at that
     * moment. Later product edits never change an existing order.
     */
    public function up(): void
    {
        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Public reference shown to the buyer and sent to the payment
            // gateway, e.g. ZTG-7K3M9QX2. Never the auto-increment id.
            $table->string('code', 20)->unique();
            // From the buyer's browser, one per checkout page. A second
            // submit with the same key returns the first checkout instead
            // of buying twice.
            $table->uuid('idempotency_key');
            $table->string('payment_status', 20)->default('pending');
            $table->bigInteger('items_total');
            $table->bigInteger('shipping_total');
            $table->bigInteger('grand_total');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['payment_status', 'expires_at']); // the expiry job
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();       // ZTG-7K3M9QX2-1
            $table->string('status', 30)->default('awaiting_payment');

            $table->bigInteger('items_total');
            $table->bigInteger('shipping_cost');
            $table->string('courier', 20);
            $table->string('courier_service', 50);
            $table->string('shipping_etd', 50)->nullable();
            $table->integer('weight_grams');

            // The address as it was. Editing or deleting it in the address
            // book later must not move a parcel that is already on its way.
            $table->string('recipient_name', 100);
            $table->string('phone', 20);
            $table->text('street');
            $table->string('address_notes', 200)->nullable();
            $table->string('province', 100);
            $table->string('city', 100);
            $table->string('district', 100);
            $table->string('subdistrict', 100);
            $table->string('postal_code', 10);
            $table->unsignedInteger('destination_id');
            $table->unsignedInteger('origin_destination_id');

            $table->timestamps();

            $table->index(['store_id', 'status']); // seller's order list
            $table->index(['user_id', 'created_at']); // buyer's order list
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // RESTRICT: a variant that was sold can never be hard-deleted.
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 200);
            $table->string('variant_label', 100)->nullable();
            $table->string('sku', 64)->nullable();
            $table->bigInteger('price');
            $table->integer('quantity');
            $table->integer('weight_grams');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE checkouts ADD CONSTRAINT checkouts_total_check CHECK (grand_total = items_total + shipping_total)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('checkouts');
    }
};
