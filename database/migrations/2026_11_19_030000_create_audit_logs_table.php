<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who did what, to what, when: for sensitive actions (moderation, money,
     * permissions). Append-only, like the ledger: rows are never changed.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Null = the system (scheduler, queue job, console command).
            // nullOnDelete: deleting a user must not erase what they did.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);              // e.g. product.banned
            $table->string('subject_type', 30);        // product, store, order, payout, user
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label', 200);      // readable at the time ("Kaos Polos")
            $table->jsonb('details')->nullable();      // reason, old/new values (masked)
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
