<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Typo-tolerant search. pg_trgm splits text into 3-letter pieces
     * ("kaos" → "  k", " ka", "kao", "aos", "os ") and compares how many
     * pieces two texts share, so "kaoss polso" still finds "Kaos Polos".
     *
     * The GIN index makes both ILIKE '%...%' and the similarity operator
     * (<%) fast: without it every search reads every product.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX products_name_trgm ON products USING gin (name gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS products_name_trgm');
        // The extension stays: dropping it could break other objects.
    }
};
