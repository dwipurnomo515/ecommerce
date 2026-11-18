<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A store's reputation: the same running totals as products, over
     * every review of every product it sold.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('rating_sum')->default(0);
        });

        // Reviews written before this column existed.
        DB::statement('
            UPDATE stores SET
                rating_count = totals.n,
                rating_sum = totals.total
            FROM (SELECT store_id, count(*) AS n, sum(rating) AS total FROM reviews GROUP BY store_id) AS totals
            WHERE totals.store_id = stores.id
        ');
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['rating_count', 'rating_sum']);
        });
    }
};
