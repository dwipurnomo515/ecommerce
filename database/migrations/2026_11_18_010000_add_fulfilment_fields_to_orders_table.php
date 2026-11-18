<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When each fulfilment step happened, plus the courier's tracking
     * number. The timestamps double as a history for disputes ("dikirim
     * tanggal berapa?") and drive the automatic completion.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_number', 50)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['status', 'shipped_at']); // the auto-complete job
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'shipped_at']);
            $table->dropColumn(['tracking_number', 'processed_at', 'shipped_at', 'completed_at']);
        });
    }
};
