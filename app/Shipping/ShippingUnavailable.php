<?php

namespace App\Shipping;

use RuntimeException;

/**
 * The shipping service is not configured, down, slow or rejected us. The
 * message is for logs; buyers get a friendly text from the controller.
 */
class ShippingUnavailable extends RuntimeException {}
