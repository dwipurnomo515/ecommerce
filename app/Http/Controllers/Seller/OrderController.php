<?php

namespace App\Http\Controllers\Seller;

use App\Actions\Orders\CancelPaidOrder;
use App\Actions\Orders\FulfilOrder;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Pesanan masuk": the seller's orders, by fulfilment status.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $store = $user->store;

        if ($store === null) {
            return to_route('seller.store.create');
        }

        // Default tab: what needs the seller's attention.
        $status = OrderStatus::tryFrom($request->string('status')->value());
        $status = in_array($status, OrderStatus::forSellers(), true) ? $status : OrderStatus::Paid;

        $orders = Order::query()
            ->where('store_id', $store->id)
            ->where('status', $status)
            ->with('items')
            // Oldest first while there is work to do (first paid, first
            // shipped); newest first for finished history.
            ->orderBy('created_at', $status === OrderStatus::Completed ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'created_at' => $o->created_at?->toIso8601String(),
                'shipped_at' => $o->shipped_at?->toIso8601String(),
                'items_total' => $o->items_total,
                'courier' => strtoupper($o->courier).' '.$o->courier_service,
                'tracking_number' => $o->tracking_number,
                'weight_grams' => $o->weight_grams,
                // What the seller needs to pack and label the parcel.
                'ship_to' => [
                    'recipient_name' => $o->recipient_name,
                    'phone' => $o->phone,
                    'street' => $o->street,
                    'notes' => $o->address_notes,
                    'region' => "{$o->subdistrict}, {$o->district}, {$o->city}, {$o->province} {$o->postal_code}",
                ],
                'items' => $o->items->map(fn (OrderItem $i) => [
                    'id' => $i->id,
                    'name' => $i->product_name,
                    'variant' => $i->variant_label,
                    'sku' => $i->sku,
                    'quantity' => $i->quantity,
                ]),
            ]);

        /** @var array<string, int> $counts */
        $counts = Order::query()
            ->where('store_id', $store->id)
            ->whereIn('status', OrderStatus::forSellers())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return Inertia::render('seller/orders/index', [
            'orders' => $orders,
            'status' => $status->value,
            'tabs' => array_map(fn (OrderStatus $s) => [
                'value' => $s->value,
                'label' => $s->sellerLabel(),
                'count' => (int) ($counts[$s->value] ?? 0),
            ], OrderStatus::forSellers()),
        ]);
    }

    public function process(Order $order, FulfilOrder $fulfil): RedirectResponse
    {
        Gate::authorize('fulfil', $order);

        return $this->attempt(fn () => $fulfil->process($order), "Pesanan {$order->number} sedang diproses.");
    }

    public function ship(Request $request, Order $order, FulfilOrder $fulfil): RedirectResponse
    {
        Gate::authorize('fulfil', $order);

        /** @var array{tracking_number: string} $data */
        $data = $request->validate([
            // Resi: letters, digits, dashes. Uppercased so "jp123" and
            // "JP123" are the same number for the buyer and the courier.
            'tracking_number' => ['required', 'string', 'min:6', 'max:40', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'tracking_number.regex' => 'Nomor resi hanya berisi huruf, angka, dan tanda strip.',
        ], ['tracking_number' => 'nomor resi']);

        return $this->attempt(
            fn () => $fulfil->ship($order, strtoupper($data['tracking_number'])),
            "Pesanan {$order->number} ditandai dikirim.",
        );
    }

    /**
     * "Batalkan pesanan" before it ships: stock back, buyer refunded.
     */
    public function cancel(Request $request, Order $order, CancelPaidOrder $cancel): RedirectResponse
    {
        Gate::authorize('fulfil', $order);

        /** @var array{reason: string} $data */
        $data = $request->validate(
            // The buyer reads this: it must explain something.
            ['reason' => ['required', 'string', 'min:10', 'max:300']],
            attributes: ['reason' => 'alasan'],
        );

        return $this->attempt(
            fn () => $cancel->handle($order, trim($data['reason'])),
            "Pesanan {$order->number} dibatalkan. Dana pembeli dikembalikan.",
        );
    }

    private function attempt(callable $step, string $done): RedirectResponse
    {
        try {
            $step();
            Inertia::flash('toast', ['type' => 'success', 'message' => $done]);
        } catch (InvalidStatusTransition) {
            // Another tab (or the buyer) changed it first. Reload shows the truth.
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Status pesanan sudah berubah. Halaman dimuat ulang.']);
        }

        return back();
    }
}
