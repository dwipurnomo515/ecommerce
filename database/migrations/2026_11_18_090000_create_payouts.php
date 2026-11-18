<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where the store's money goes. One account per store.
        Schema::table('stores', function (Blueprint $table) {
            $table->string('payout_bank', 20)->nullable();
            $table->string('payout_account_number', 30)->nullable();
            $table->string('payout_account_name', 100)->nullable();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            // Copied at request time: changing the bank account later must
            // not redirect a transfer that is already on its way.
            $table->string('bank', 20);
            $table->string('account_number', 30);
            $table->string('account_name', 100);
            // pending → paid | failed
            $table->string('status', 20)->default('pending');
            $table->string('reference', 100)->nullable();       // from the transfer provider
            $table->string('failure_reason', 200)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'id']);
        });

        DB::statement('ALTER TABLE payouts ADD CONSTRAINT payouts_amount_check CHECK (amount > 0)');
        // At most one payout in flight per store: a second request while one
        // is pending is refused by the database itself.
        DB::statement("CREATE UNIQUE INDEX payouts_one_pending_per_store ON payouts (store_id) WHERE status = 'pending'");

        // Ledger lines can point at a payout; each payout is debited and (if
        // it fails) reversed at most once.
        Schema::table('store_ledger_entries', function (Blueprint $table) {
            $table->foreignId('payout_id')->nullable()->constrained()->restrictOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX store_ledger_once_per_payout ON store_ledger_entries (payout_id, type) WHERE payout_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('store_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_id');
        });
        Schema::dropIfExists('payouts');
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['payout_bank', 'payout_account_number', 'payout_account_name']);
        });
    }
};
