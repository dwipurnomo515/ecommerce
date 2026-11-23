<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
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

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard', [
            'buyer' => $this->buyer($user),
        ]);
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
