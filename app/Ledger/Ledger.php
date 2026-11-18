<?php

namespace App\Ledger;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY way a store's balance changes: write an entry and move the
 * balance in the same transaction, with the store row locked.
 */
class Ledger
{
    public function post(int $storeId, ?Order $order, string $type, int $amount, string $description): LedgerEntry
    {
        return DB::transaction(function () use ($storeId, $order, $type, $amount, $description): LedgerEntry {
            // Locked: two postings for the same store queue up, so each
            // "balance_after" is exact and the balance can't go negative
            // between a check and a write (payouts, next step).
            $store = Store::query()->lockForUpdate()->findOrFail($storeId);
            $store->balance += $amount; // CHECK (balance >= 0) is the backstop
            $store->save();

            $entry = new LedgerEntry;
            $entry->forceFill([
                'store_id' => $storeId,
                'order_id' => $order?->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $store->balance,
                'description' => $description,
                'created_at' => now(),
            ])->save();

            return $entry;
        });
    }

    /**
     * A completed order pays the store: income, then the commission as its
     * own line (so the statement shows exactly what Zetago took).
     * The unique (order_id, type) index makes a second call fail instead
     * of paying twice.
     */
    public function creditCompletedOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $this->post($order->store_id, $order, LedgerEntry::ORDER_INCOME,
                $order->items_total + $order->shipping_cost,
                "Pesanan {$order->number} selesai");

            if ($order->commission() > 0) {
                $percent = rtrim(rtrim(number_format($order->commission_bps / 100, 2, ',', ''), '0'), ',');
                $this->post($order->store_id, $order, LedgerEntry::COMMISSION,
                    -$order->commission(),
                    "Komisi Zetago {$percent}% pesanan {$order->number}");
            }
        });
    }
}
