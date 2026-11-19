<?php

namespace App\Checkout;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Store;
use App\Models\User;
use App\Shipping\ShippingOption;
use App\Shipping\ShippingProvider;
use App\Shipping\ShippingUnavailable;
use App\Shipping\UnknownRegion;
use App\Support\CartView;
use Illuminate\Support\Facades\Log;

/**
 * What checkout would look like right now: the buyable cart items per
 * store, each store's parcel weight and its shipping options to the chosen
 * address. Placing the order (next step) rebuilds this on the server, so
 * the buyer is always charged from the same calculation they were shown.
 */
class CheckoutPreview
{
    /** Per-store shipping situations the page has to explain. */
    public const SHIPPING_OK = 'ok';

    public const NO_ADDRESS = 'no_address';

    public const NO_ORIGIN = 'no_origin';

    public const NO_COURIER = 'no_courier';

    public const UNAVAILABLE = 'unavailable';

    /** The buyer's address region is unknown to the shipping service. */
    public const ADDRESS_UNKNOWN = 'address_unknown';

    /** The store's shipping origin is unknown to the shipping service. */
    public const ORIGIN_UNKNOWN = 'origin_unknown';

    public function __construct(private readonly ShippingProvider $shipping) {}

    /**
     * @return array{stores: list<array<string, mixed>>, items_total: int, has_problems: bool}
     */
    public function build(User $user, ?Address $address): array
    {
        $cart = CartView::for($user);

        // Weights stay on the server (they are not shown to buyers).
        $weights = $user->cartItems()->with('variant:id,weight_grams')->get()
            ->mapWithKeys(fn (CartItem $i) => [$i->id => $i->variant->weight_grams * $i->quantity]);

        $origins = Store::query()
            ->whereKey(array_column($cart['stores'], 'id'))
            ->get(['id', 'origin_destination_id', 'origin_city'])
            ->keyBy('id');

        $stores = [];

        foreach ($cart['stores'] as $group) {
            /** @var list<array<string, mixed>> $items */
            $items = array_values(array_filter($group['items'], fn (array $i) => $i['status'] === CartView::OK));

            if ($items === []) {
                continue; // nothing buyable from this store right now
            }

            $weight = (int) array_sum(array_map(fn (array $i) => $weights[$i['id']] ?? 0, $items));
            /** @var Store $store */
            $store = $origins->get($group['id']);

            $stores[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'slug' => $group['slug'],
                'origin_city' => $store->origin_city,
                'origin_destination_id' => $store->origin_destination_id,
                'items' => $items,
                'subtotal' => $group['subtotal'],
                'weight' => $weight,
                'shipping' => $this->shippingFor($store, $address, $weight),
            ];
        }

        return [
            'stores' => $stores,
            'items_total' => (int) array_sum(array_column($stores, 'subtotal')),
            'has_problems' => $cart['has_problems'],
        ];
    }

    /**
     * @return array{status: string, options: list<array<string, mixed>>}
     */
    private function shippingFor(Store $store, ?Address $address, int $grams): array
    {
        if ($address === null) {
            return ['status' => self::NO_ADDRESS, 'options' => []];
        }

        if (! $store->hasShippingOrigin()) {
            return ['status' => self::NO_ORIGIN, 'options' => []];
        }

        try {
            /** @var int $origin hasShippingOrigin() checked it */
            $origin = $store->origin_destination_id;
            $options = $this->shipping->quote($origin, $address->destination_id, $grams);
        } catch (UnknownRegion $e) {
            Log::notice('Shipping region unknown', ['store' => $store->id, 'address' => $address->id, 'error' => $e->getMessage()]);

            return [
                'status' => $e->side === UnknownRegion::ORIGIN ? self::ORIGIN_UNKNOWN : self::ADDRESS_UNKNOWN,
                'options' => [],
            ];
        } catch (ShippingUnavailable $e) {
            Log::warning('Shipping quote failed', ['store' => $store->id, 'error' => $e->getMessage()]);

            return ['status' => self::UNAVAILABLE, 'options' => []];
        }

        return [
            'status' => $options === [] ? self::NO_COURIER : self::SHIPPING_OK,
            'options' => array_map(fn (ShippingOption $o) => $o->toArray(), $options),
        ];
    }
}
