<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the store ships from: a RajaOngkir destination id (to price
     * shipping) plus a copy of its names (to show "Dikirim dari ...").
     * Nullable: stores opened before this existed fill it in later, and
     * their products can't be checked out until they do.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedInteger('origin_destination_id')->nullable();
            $table->string('origin_label', 255)->nullable();
            $table->string('origin_city', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['origin_destination_id', 'origin_label', 'origin_city']);
        });
    }
};
