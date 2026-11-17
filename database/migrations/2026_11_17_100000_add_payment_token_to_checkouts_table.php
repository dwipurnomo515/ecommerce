<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Midtrans Snap token for this checkout. A payment gateway order id
     * can be used ONCE, so the token is created on the first "Bayar" click
     * and reused for every later click (it stays valid until the checkout's
     * deadline).
     */
    public function up(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->string('payment_token', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropColumn('payment_token');
        });
    }
};
