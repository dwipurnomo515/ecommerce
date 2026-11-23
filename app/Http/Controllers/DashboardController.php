<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payout;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Ringkasan": the first page after login. What needs this person's
 * attention right now, per role. Counts and short lists only; each
 * links to the full page that already exists.
 */
class DashboardController extends Controller
{
    /** Orders still on their way to the buyer. */
    private const ACTIVE = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped];

    /** Same threshold as the "Sisa N" hint on the product page. */
    private const LOW_STOCK = 5;

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard', [
            'buyer' => $this->buyer($user),
            // Only for users who opened a store.
            'seller' => $user->store === null ? null : $this->seller($user->store),
            // Same gate as the admin routes, not just a hidden menu.
            'admin' => $user->can('admin') ? $this->admin() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function admin(): array
    {
        $pending = Store::query()->where('status', StoreStatus::Pending);

        return [
            'counts' => [
                'pending_stores' => (clone $pending)->count(),
                // New this week: older ones were already seen in the
                // review list, so they shouldn't nag forever.
                'low_reviews' => Review::query()
                    ->where('rating', '<=', 2)
                    ->whereNull('hidden_at')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'payouts' => Payout::query()->needsAttention()->count(),
                'refunds' => Refund::query()->needsAttention()->count(),
            ],
            // First come, first served, as on the "Toko" page.
            'pending_stores' => $pending
                ->with('user:id,name')
                ->oldest()
                ->orderBy('id')
                ->limit(5)
                ->get()
                ->map(fn (Store $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'owner' => $s->user->name,
                    'created_at' => $s->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seller(Store $store): array
    {
        $counts = Order::query()
            ->where('store_id', $store->id)
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Processing])
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Same formula as the "Saldo" page: paid, not completed yet.
        $held = (int) Order::query()
            ->where('store_id', $store->id)
            ->whereIn('status', Order::HELD_STATUSES)
            ->sum(DB::raw(Order::SELLER_EARNING_SQL));

        // On sale now and (almost) gone: sold out first. Draft, archived
        // and banned products don't sell, so their stock doesn't matter.
        $lowStock = ProductVariant::query()
            ->where('is_active', true)
            ->where('stock', '<=', self::LOW_STOCK)
            ->whereHas('product', fn ($q) => $q
                ->where('store_id', $store->id)
                ->where('status', ProductStatus::Active));

        return [
            'store' => [
                'name' => $store->name,
                'status' => $store->status->value,
                'status_label' => $store->status->label(),
            ],
            'counts' => [
                'to_process' => (int) ($counts[OrderStatus::Paid->value] ?? 0),
                'to_ship' => (int) ($counts[OrderStatus::Processing->value] ?? 0),
                'unanswered_reviews' => Review::query()
                    ->where('store_id', $store->id)
                    ->whereNull('seller_reply')
                    ->whereNull('hidden_at')
                    ->count(),
                'low_stock' => (clone $lowStock)->count(),
            ],
            'balance' => [
                'available' => $store->balance,
                'held' => $held,
                'can_withdraw' => $store->balance >= Payout::MINIMUM,
            ],
            'low_stock' => $lowStock
                ->with('product:id,name')
                ->orderBy('stock')
                ->orderBy('id')
                ->limit(5)
                ->get()
                ->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'product_id' => $v->product_id,
                    'product' => $v->product->name,
                    'variant' => implode(' / ', array_values($v->options)),
                    'stock' => $v->stock,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buyer(User $user): array
    {
        // Still payable: pending AND before its deadline (expired ones are
        // cancelled by the scheduler within minutes, but not instantly).
        $unpaid = Checkout::query()
            ->where('user_id', $user->id)
            ->where('payment_status', PaymentStatus::Pending)
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->get(['code', 'grand_total', 'expires_at']);

        $counts = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', self::ACTIVE)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $active = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', self::ACTIVE)
            ->with(['store:id,name', 'checkout:id,code'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Order $o) => [
                'number' => $o->number,
                'store' => $o->store->name,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'tracking_number' => $o->tracking_number,
                'checkout_code' => $o->checkout->code,
            ]);

        return [
            'unpaid' => $unpaid->map(fn (Checkout $c) => [
                'code' => $c->code,
                'grand_total' => $c->grand_total,
                'expires_at' => $c->expires_at->toIso8601String(),
            ])->all(),
            'counts' => [
                'unpaid' => $unpaid->count(),
                'in_progress' => (int) ($counts[OrderStatus::Paid->value] ?? 0) + (int) ($counts[OrderStatus::Processing->value] ?? 0),
                'shipped' => (int) ($counts[OrderStatus::Shipped->value] ?? 0),
            ],
            'active' => $active->all(),
        ];
    }
}
