<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seller stores. A user may own at most one store; buyers don't need one.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            // unique() = one store per user, enforced by the database itself.
            // restrictOnDelete: a user who owns a store can't be deleted while
            // the store (and later its orders) still exists.
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            $table->string('name', 100);

            // Store URL: /toko-budi
            $table->string('slug', 120)->unique();

            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();

            // pending -> active (approved by admin) -> suspended (by admin).
            // A string instead of a DB enum: adding a new status later is just
            // code, not a schema change. Allowed values live in a PHP enum.
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
