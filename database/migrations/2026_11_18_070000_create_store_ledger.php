<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The commission rate that applied when the order was placed.
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('commission_bps')->default(0);
        });
        // Orders placed before commissions existed: today's rate.
        DB::table('orders')->update(['commission_bps' => (int) config('marketplace.commission_bps', 500)]);

        // The store's money, as a book of entries. A row is NEVER updated
        // or deleted: a mistake is fixed by a new, opposite entry. That is
        // how bookkeeping stays auditable ("why is my balance X?").
        Schema::create('store_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            // order_income, commission (later: payout, refund, adjustment)
            $table->string('type', 30);
            // Signed whole Rupiah: + money in, - money out.
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('description', 200);
            $table->timestamp('created_at');

            $table->index(['store_id', 'id']); // the store's statement, in order
        });

        // An order is credited at most once per kind of entry, even if a
        // bug or a re-run job tries again. Partial: payouts have no order.
        DB::statement('CREATE UNIQUE INDEX store_ledger_once_per_order ON store_ledger_entries (order_id, type) WHERE order_id IS NOT NULL');

        // The current balance, kept next to the store for quick reads and
        // for locking when money goes out. Never negative.
        Schema::table('stores', function (Blueprint $table) {
            $table->bigInteger('balance')->default(0);
        });
        DB::statement('ALTER TABLE stores ADD CONSTRAINT stores_balance_check CHECK (balance >= 0)');
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
        Schema::dropIfExists('store_ledger_entries');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('commission_bps');
        });
    }
};
