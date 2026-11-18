<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Reviews\WriteReview;
use App\Checkout\CheckoutPreview;
use App\Checkout\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\User;
use App\Payments\PaymentGateway;
use App\Payments\ReconcilePayment;
use App\Payments\StartPayment;
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
     * "Bayar sekarang": the token comes back as flash data; the page opens
     * the Midtrans popup when it arrives.
     */
    public function pay(Request $request, string $code, StartPayment $startPayment): RedirectResponse
    {
        $checkout = $this->own($request, $code);

        Inertia::flash('payment', ['token' => $startPayment->handle($checkout)]);

        return back();
    }

    /**
     * Only its owner: another buyer's code is simply not found.
     */
    private function own(Request $request, string $code): Checkout
    {
        return Checkout::query()
            ->where('code', $code)
            ->where('user_id', $request->user()?->id)
            ->firstOrFail();
    }

    /**
     * "Pesanan dibuat": what was ordered and what to pay before when.
     */
    public function placed(Request $request, string $code, PaymentGateway $gateway, ReconcilePayment $reconcile, WriteReview $writeReview): Response
    {
        $checkout = $this->own($request, $code);

        // The buyer is looking at "Menunggu pembayaran" right after paying:
        // if the webhook hasn't arrived (yet), ask Midtrans. At most every
        // 30 s per checkout, so reloading the page can't flood the API.
        $reconcile->handle($checkout);

        $checkout->load(['orders.store:id,name', 'orders.refund', 'orders.items.review:id,order_item_id,rating', 'orders.items.order']);
        // A refund of the WHOLE checkout (paid after it was cancelled).
        $checkoutRefund = Refund::query()->where('checkout_id', $checkout->id)->whereNull('order_id')->first();
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('shop/checkout-placed', [
            // Public client key + snap.js URL (never the server key).
            'payment' => $gateway->clientConfig(),
            'checkout' => [
                'code' => $checkout->code,
                'payment_status' => $checkout->payment_status->value,
                'payment_status_label' => $checkout->payment_status->label(),
                'items_total' => $checkout->items_total,
                'shipping_total' => $checkout->shipping_total,
                'grand_total' => $checkout->grand_total,
                'expires_at' => $checkout->expires_at->toIso8601String(),
                'refund' => $checkoutRefund ? ['amount' => $checkoutRefund->amount, 'label' => $checkoutRefund->label()] : null,
                'orders' => $checkout->orders->map(fn (Order $o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'status' => $o->status->value,
                    'tracking_number' => $o->tracking_number,
                    'shipped_at' => $o->shipped_at?->toIso8601String(),
                    'auto_complete_at' => $o->shipped_at?->addDays(Order::AUTO_COMPLETE_DAYS)->toIso8601String(),
                    'cancel_reason' => $o->cancel_reason,
                    'refund' => $o->refund ? ['amount' => $o->refund->amount, 'label' => $o->refund->label()] : null,
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
                        // The stars already given, or whether it can be reviewed now.
                        'review_rating' => $i->review?->rating,
                        'can_review' => $writeReview->refusal($user, $i) === null,
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
