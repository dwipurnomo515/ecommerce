<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The price the buyer saw when they last added or changed this item.
     * NEVER used to charge: the cart always bills today's price. It only
     * lets the cart say "harga naik dari Rp 75.000" instead of surprising
     * the buyer at checkout.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->bigInteger('seen_price')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('seen_price');
        });
    }
};
