<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admins can hide a review (spam, abuse, personal data). Hidden, not
     * deleted: it can be restored, and the audit log says who and why.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable();
            $table->string('hidden_reason', 300)->nullable();
        });

        // Running totals must never drop below zero, whatever a bug does.
        foreach (['products', 'stores'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_rating_totals_check CHECK (rating_count >= 0 AND rating_sum >= 0)");
        }
    }

    public function down(): void
    {
        foreach (['products', 'stores'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_rating_totals_check");
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['hidden_at', 'hidden_reason']);
        });
    }
};
