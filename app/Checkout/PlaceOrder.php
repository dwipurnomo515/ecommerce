<?php

namespace App\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\PublicCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns the buyer's cart into a checkout (one payment) with one order per
 * store, takes the stock, and empties those cart lines.
 *
 * The browser sends only CHOICES (address, "jne:REG" per store) and the
 * total it showed. Every amount is recalculated here; if it no longer
 * matches what the buyer saw, nothing is bought and they are told why.
 */
class PlaceOrder
{
    public function __construct(private readonly CheckoutPreview $preview) {}

    /**
     * @param  array<int|string, string>  $choices  store id => shipping option key
     *
     * @throws ValidationException with a message for the buyer under "checkout"
     */
    public function handle(User $user, Address $address, array $choices, string $idempotencyKey, int $expectedTotal): Checkout
    {
        // A double click / retried request with the same key: answer with
        // the checkout the first one made, don't buy twice.
        if ($existing = $this->existing($user, $idempotencyKey)) {
            return $existing;
        }

        // Rebuilt from the database and the shipping API. Deliberately
        // OUTSIDE the transaction: holding row locks while waiting for an
        // outside HTTP call would block other buyers for seconds.
        $preview = $this->preview->build($user, $address);
        $shipping = $this->chosenShipping($preview, $choices);
        $shippingTotal = (int) array_sum(array_column($shipping, 'cost'));

        if ($expectedTotal !== $preview['items_total'] + $shippingTotal) {
            $this->fail('Harga, stok atau ongkir berubah sejak halaman dibuka. Periksa lagi totalnya sebelum membuat pesanan.');
        }

        try {
            return DB::transaction(fn () => $this->create($user, $address, $preview, $shipping, $shippingTotal, $idempotencyKey));
        } catch (UniqueConstraintViolationException $e) {
            // Two requests with the same key raced past existing(): the
            // unique index let only one in. Return that one.
            return $this->existing($user, $idempotencyKey) ?? throw $e;
        }
    }

    /**
     * @param  array{stores: list<array<string, mixed>>, items_total: int}  $preview
     * @param  array<string, array<string, mixed>>  $shipping  store id => option
     */
    private function create(User $user, Address $address, array $preview, array $shipping, int $shippingTotal, string $key): Checkout
    {
        /** @var list<int> $cartIds */
        $cartIds = collect($preview['stores'])->flatMap(fn (array $s) => array_column($s['items'], 'id'))->all();

        /** @var Collection<int, CartItem> $cart */
        $cart = $user->cartItems()->whereKey($cartIds)->get()->keyBy('id');

        // Lock every variant we are about to sell, ALWAYS in id order. Two
        // checkouts locking the same two variants in opposite orders would
        // wait for each other forever (a deadlock).
        $variants = ProductVariant::query()
            ->whereKey($cart->pluck('product_variant_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // Visibility may have changed since the preview (seller archived it,
        // admin banned it): checked again now that the rows are locked.
        $visible = Product::query()->visibleToBuyers()->whereKey($variants->pluck('product_id'))->pluck('id')->flip();

        $checkout = new Checkout;
        $checkout->user_id = $user->id;
        $checkout->code = PublicCode::make();
        $checkout->idempotency_key = $key;
        $checkout->payment_status = PaymentStatus::Pending;
        $checkout->items_total = $preview['items_total'];
        $checkout->shipping_total = $shippingTotal;
        $checkout->grand_total = $preview['items_total'] + $shippingTotal;
        $checkout->expires_at = now()->addHours(Checkout::PAYMENT_WINDOW_HOURS);
        $checkout->save();

        foreach ($preview['stores'] as $n => $store) {
            $option = $shipping[$store['id']];
            $order = $this->newOrder($checkout, $user, $address, $store, $option, $n + 1);

            foreach ($store['items'] as $line) {
                $item = $cart->get($line['id']);
                $variant = $item ? $variants->get($item->product_variant_id) : null;

                if ($item === null || $variant === null || $item->quantity !== $line['quantity']) {
                    $this->fail('Keranjang Anda berubah (mungkin dari tab lain). Periksa lagi sebelum membuat pesanan.');
                }

                if (! $variant->is_active || ! $visible->has($variant->product_id)) {
                    $this->fail("{$line['name']} baru saja berhenti dijual.");
                }

                if ($variant->price !== $line['price']) {
                    $this->fail("Harga {$line['name']} baru saja berubah. Periksa lagi totalnya.");
                }

                if ($variant->stock < $item->quantity) {
                    $this->fail("Stok {$line['name']} baru saja habis atau berkurang.");
                }

                // Safe: the row is locked. The CHECK (stock >= 0) constraint
                // is the last line of defence if this logic is ever wrong.
                $variant->decrement('stock', $item->quantity);

                $order->items()->forceCreate([
                    'product_variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => $line['name'],
                    'variant_label' => $line['variant'] !== '' ? $line['variant'] : null,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'quantity' => $item->quantity,
                    'weight_grams' => $variant->weight_grams,
                ]);
            }
        }

        $user->cartItems()->whereKey($cartIds)->delete();

        return $checkout;
    }

    /**
     * @param  array<string, mixed>  $store  one store from the preview
     * @param  array<string, mixed>  $option  the chosen ShippingOption::toArray()
     */
    private function newOrder(Checkout $checkout, User $user, Address $address, array $store, array $option, int $n): Order
    {
        $order = new Order;
        $order->forceFill([
            'checkout_id' => $checkout->id,
            'user_id' => $user->id,
            'store_id' => $store['id'],
            'number' => "{$checkout->code}-{$n}",
            'status' => OrderStatus::AwaitingPayment,
            // Frozen now: a later rate change never touches this order.
            'commission_bps' => config()->integer('marketplace.commission_bps'),
            'items_total' => $store['subtotal'],
            'shipping_cost' => $option['cost'],
            'courier' => $option['courier'],
            'courier_service' => $option['service'],
            'shipping_etd' => $option['etd'] !== '' ? $option['etd'] : null,
            'weight_grams' => $store['weight'],
            // A copy of the address, see the migration.
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'street' => $address->street,
            'address_notes' => $address->notes,
            'province' => $address->province,
            'city' => $address->city,
            'district' => $address->district,
            'subdistrict' => $address->subdistrict,
            'postal_code' => $address->postal_code,
            'destination_id' => $address->destination_id,
            'origin_destination_id' => $store['origin_destination_id'],
        ])->save();

        return $order;
    }

    /**
     * The option the buyer picked for each store, taken from the SERVER's
     * fresh quote. An unknown key ("jne:MURAH", or a price typed into the
     * request) simply isn't in the list.
     *
     * @param  array{stores: list<array<string, mixed>>}  $preview
     * @param  array<int|string, string>  $choices
     * @return array<string, array<string, mixed>>
     */
    private function chosenShipping(array $preview, array $choices): array
    {
        if ($preview['stores'] === []) {
            $this->fail('Tidak ada barang di keranjang yang bisa dibeli saat ini.');
        }

        $chosen = [];

        foreach ($preview['stores'] as $store) {
            if ($store['shipping']['status'] !== CheckoutPreview::SHIPPING_OK) {
                $this->fail("Pengiriman dari {$store['name']} belum bisa dihitung.");
            }

            $key = $choices[$store['id']] ?? null;
            $option = null;

            /** @var list<array<string, mixed>> $options */
            $options = $store['shipping']['options'];
            foreach ($options as $candidate) {
                if ($candidate['key'] === $key) {
                    $option = $candidate;
                }
            }

            if ($option === null) {
                $this->fail("Pilih kurir untuk pesanan dari {$store['name']}.");
            }

            $chosen[$store['id']] = $option;
        }

        return $chosen;
    }

    private function existing(User $user, string $key): ?Checkout
    {
        return Checkout::query()->where('user_id', $user->id)->where('idempotency_key', $key)->first();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['checkout' => $message]);
    }
}
