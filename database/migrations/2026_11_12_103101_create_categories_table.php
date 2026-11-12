<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global product categories, managed by admins (sellers only pick one).
     * Tree via parent_id; the max depth of 3 levels is enforced in the app.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // NULL = top-level category. restrictOnDelete: a category that still
            // has sub-categories cannot be deleted (no orphaned children).
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->restrictOnDelete();

            $table->string('name', 100);

            // Unique across the whole marketplace so URLs stay short: /c/kaos-pria
            $table->string('slug', 120)->unique();

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // PostgreSQL does NOT index foreign keys automatically. This one also
            // covers the most common query: "children of X, in display order".
            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
