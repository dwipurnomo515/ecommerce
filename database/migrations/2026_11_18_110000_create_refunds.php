<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });

        // Money going back to a buyer. Either for one store's order (the
        // seller cancelled it) or for a whole checkout (paid after it had
        // already been cancelled).
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->string('reason', 300);
            $table->string('status', 20)->default('pending'); // pending → refunded | failed
            $table->string('reference', 100)->nullable();
            $table->string('failure_reason', 200)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_check CHECK (amount > 0)');
        // Never refund the same money twice: once per order, and once per
        // whole checkout (the order_id IS NULL kind).
        DB::statement('CREATE UNIQUE INDEX refunds_once_per_order ON refunds (order_id) WHERE order_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX refunds_once_per_checkout ON refunds (checkout_id) WHERE order_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cancel_reason', 'cancelled_at']);
        });
    }
};
