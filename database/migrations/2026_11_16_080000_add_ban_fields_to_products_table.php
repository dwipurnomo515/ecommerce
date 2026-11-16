<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why an admin banned a product, shown to the seller so they know what
     * to fix. Both are NULL unless status = 'banned'.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('ban_reason')->nullable();
            $table->timestamp('banned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['ban_reason', 'banned_at']);
        });
    }
};
