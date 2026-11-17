<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();     // e.g. bank_transfer, qris
            $table->string('payment_reference', 100)->nullable(); // Midtrans transaction_id
        });

        // Every notification Midtrans sends, as received. An audit trail
        // for "the buyer says they paid": what arrived, when, and whether
        // its signature was valid. Nothing here is ever updated.
        Schema::create('payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_id', 50);
            $table->string('transaction_status', 30)->nullable();
            $table->boolean('signature_valid');
            $table->string('outcome', 30); // what we did with it
            $table->jsonb('payload');
            $table->timestamp('created_at');

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notifications');

        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropColumn(['paid_at', 'payment_method', 'payment_reference']);
        });
    }
};
