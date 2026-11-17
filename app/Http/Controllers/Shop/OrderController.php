<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Pesanan saya": the buyer's purchases, newest first. One row per
 * checkout (= one payment), listing the stores in it.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $checkouts = Checkout::query()
            ->where('user_id', $user->id)
            ->with(['orders' => fn ($q) => $q->with('store:id,name')->withCount('items')])
            ->latest()
            ->orderByDesc('id')
            ->paginate(10)
            ->through(fn (Checkout $c) => [
                'code' => $c->code,
                'created_at' => $c->created_at?->toIso8601String(),
                'payment_status' => $c->payment_status->value,
                'payment_status_label' => $c->payment_status->label(),
                'grand_total' => $c->grand_total,
                'expires_at' => $c->expires_at->toIso8601String(),
                'stores' => $c->orders->map(fn (Order $o) => [
                    'name' => $o->store->name,
                    'items_count' => (int) $o->items_count,
                ]),
            ]);

        return Inertia::render('shop/orders', ['checkouts' => $checkouts]);
    }
}
