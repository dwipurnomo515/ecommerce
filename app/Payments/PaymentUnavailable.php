<?php

namespace App\Payments;

use RuntimeException;

/** The payment provider is not configured, down or refused the request. */
class PaymentUnavailable extends RuntimeException {}
