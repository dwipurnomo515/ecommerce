<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Saldo": what the store has, what is still on its way (escrow), and the
 * statement that explains every Rupiah.
 */
class BalanceController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $store = $user->store;

        if ($store === null) {
            return to_route('seller.store.create');
        }

        // Paid but not completed yet: money the store will receive once the
        // buyer confirms. Same formula as Order::sellerEarning(); Postgres
        // integer division also rounds the commission down.
        $held = (int) Order::query()
            ->where('store_id', $store->id)
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped])
            ->sum(DB::raw('items_total + shipping_cost - (items_total * commission_bps / 10000)'));

        $entries = LedgerEntry::query()
            ->where('store_id', $store->id)
            ->orderByDesc('id')
            ->paginate(30)
            ->through(fn (LedgerEntry $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'description' => $e->description,
                'amount' => $e->amount,
                'balance_after' => $e->balance_after,
                'created_at' => $e->created_at->toIso8601String(),
            ]);

        return Inertia::render('seller/balance/index', [
            'balance' => $store->balance,
            'held' => $held,
            'commissionPercent' => config()->integer('marketplace.commission_bps') / 100,
            'entries' => $entries,
        ]);
    }
}
