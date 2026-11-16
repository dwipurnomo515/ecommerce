<?php

namespace App\Exceptions;

use BackedEnum;
use RuntimeException;

/**
 * Thrown when a business object is asked to move to a status that its
 * current status does not allow (e.g. approving an already active store).
 */
class InvalidStatusTransition extends RuntimeException
{
    public static function from(BackedEnum $current, BackedEnum $next): self
    {
        return new self("Cannot change status from [{$current->value}] to [{$next->value}].");
    }
}
