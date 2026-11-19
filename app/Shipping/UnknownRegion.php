<?php

namespace App\Shipping;

/**
 * The shipping service does not know the origin or the destination region,
 * e.g. an address saved with a sample region, or an ID the provider retired.
 *
 * Unlike an outage, reloading will not help: someone has to pick the region
 * again. Extends ShippingUnavailable so code that only cares about "no
 * quote" keeps working.
 */
class UnknownRegion extends ShippingUnavailable
{
    public const ORIGIN = 'origin';

    public const DESTINATION = 'destination';

    /**
     * @param  self::ORIGIN|self::DESTINATION  $side
     */
    public function __construct(public readonly string $side, string $message)
    {
        parent::__construct($message);
    }
}
