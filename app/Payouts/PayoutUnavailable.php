<?php

namespace App\Payouts;

use RuntimeException;

/** Temporary: provider down, timeout. The payout job retries later. */
class PayoutUnavailable extends RuntimeException {}
