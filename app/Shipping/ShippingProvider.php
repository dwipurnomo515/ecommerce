<?php

namespace App\Shipping;

/**
 * What the shop needs from a shipping service. The app talks to THIS,
 * never to RajaOngkir directly, so switching to another provider (or a
 * fake in tests) is one binding in AppServiceProvider.
 */
interface ShippingProvider
{
    /**
     * Places matching a city, district, village or postal code.
     *
     * @return list<Destination>
     *
     * @throws ShippingUnavailable when the service can't answer right now
     */
    public function searchDestinations(string $query): array;
}
