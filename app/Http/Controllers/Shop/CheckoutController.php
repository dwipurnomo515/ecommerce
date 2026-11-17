<?php

namespace App\Http\Controllers\Shop;

use App\Checkout\CheckoutPreview;
use App\Checkout\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    /**
     * GET /checkout?alamat={id}: preview with the chosen (or primary)
     * address. Changing the address is a new GET, so the server re-quotes
     * shipping for it.
     */
    public function show(Request $request, CheckoutPreview $preview): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $addresses = $user->addresses()->get();

        $address = $request->filled('alamat')
            // Through the user's own addresses: someone else's id is a 404.
            ? $user->addresses()->findOrFail($request->integer('alamat'))
            : $addresses->firstWhere('is_primary', true);

        $checkout = $preview->build($user, $address);

        if ($checkout['stores'] === []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Tidak ada barang di keranjang yang bisa dibeli saat ini.']);

            return to_route('cart.index');
        }

        return Inertia::render('shop/checkout', [
            'checkout' => $checkout,
            'addresses' => $addresses->map(fn (Address $a) => [
                'id' => $a->id,
                'label' => $a->label,
                'recipient_name' => $a->recipient_name,
                'phone' => $a->phone,
                'street' => $a->street,
                'region' => $a->regionLabel(),
                'is_primary' => $a->is_primary,
            ]),
            'addressId' => $address?->id,
        ]);
    }

    public function store(Request $request, PlaceOrder $placeOrder): RedirectResponse
    {
        /** @var array{address_id: int, shipping: array<int|string, string>, idempotency_key: string, expected_total: int} $data */
        $data = $request->validate([
            'address_id' => ['required', 'integer'],
            // { "12": "jne:REG", "15": "sicepat:REG" }: store id => option key
            'shipping' => ['required', 'array'],
            'shipping.*' => ['string', 'max:80'],
            'idempotency_key' => ['required', 'uuid'],
            'expected_total' => ['required', 'integer', 'min:0'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $checkout = $placeOrder->handle(
            $user,
            $user->addresses()->findOrFail($data['address_id']),
            $data['shipping'],
            $data['idempotency_key'],
            (int) $data['expected_total'],
        );

        return to_route('checkout.placed', $checkout->code);
    }

    /**
     * "Pesanan dibuat": what was ordered and what to pay before when.
     */
    public function placed(Request $request, string $code): Response
    {
        $checkout = Checkout::query()
            ->where('code', $code)
            // Only its owner: another buyer's code is simply not found.
            ->where('user_id', $request->user()?->id)
            ->with(['orders.store:id,name', 'orders.items'])
            ->firstOrFail();

        return Inertia::render('shop/checkout-placed', [
            'checkout' => [
                'code' => $checkout->code,
                'payment_status' => $checkout->payment_status->value,
                'payment_status_label' => $checkout->payment_status->label(),
                'items_total' => $checkout->items_total,
                'shipping_total' => $checkout->shipping_total,
                'grand_total' => $checkout->grand_total,
                'expires_at' => $checkout->expires_at->toIso8601String(),
                'orders' => $checkout->orders->map(fn (Order $o) => [
                    'number' => $o->number,
                    'store' => $o->store->name,
                    'status_label' => $o->status->label(),
                    'courier' => strtoupper($o->courier).' '.$o->courier_service,
                    'shipping_cost' => $o->shipping_cost,
                    'items_total' => $o->items_total,
                    'items' => $o->items->map(fn (OrderItem $i) => [
                        'id' => $i->id,
                        'name' => $i->product_name,
                        'variant' => $i->variant_label,
                        'price' => $i->price,
                        'quantity' => $i->quantity,
                    ]),
                ]),
                'address' => ($first = $checkout->orders->first()) ? [
                    'recipient_name' => $first->recipient_name,
                    'phone' => $first->phone,
                    'street' => $first->street,
                    'region' => "{$first->subdistrict}, {$first->district}, {$first->city}, {$first->province} {$first->postal_code}",
                ] : null,
            ],
        ]);
    }
}
