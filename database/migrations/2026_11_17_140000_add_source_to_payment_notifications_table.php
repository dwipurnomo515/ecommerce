<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the payment news came from: Midtrans pushing it (webhook) or us
     * asking (status_check, when a webhook was missed).
     */
    public function up(): void
    {
        Schema::table('payment_notifications', function (Blueprint $table) {
            $table->string('source', 20)->default('webhook');
        });
    }

    public function down(): void
    {
        Schema::table('payment_notifications', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
