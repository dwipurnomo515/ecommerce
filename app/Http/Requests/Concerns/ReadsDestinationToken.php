<?php

namespace App\Http\Requests\Concerns;

use App\Shipping\Destination;
use Illuminate\Validation\Validator;

/**
 * For form requests that receive a region picked in the DestinationPicker:
 * turns the encrypted token back into a Destination, or adds a validation
 * error if it was tampered with. See Destination::token() for why.
 */
trait ReadsDestinationToken
{
    private ?Destination $decodedDestination = null;

    /** Call from after(). Empty field = "not changed", not an error. */
    protected function decodeDestinationToken(Validator $validator, string $field, string $message): void
    {
        $token = $this->input($field);

        if (! is_string($token) || $token === '') {
            return;
        }

        $this->decodedDestination = Destination::fromToken($token);

        if ($this->decodedDestination === null) {
            $validator->errors()->add($field, $message);
        }
    }

    protected function decodedDestination(): ?Destination
    {
        return $this->decodedDestination;
    }
}
