<?php

namespace App\Actions\Addresses;

use App\Http\Requests\Settings\AddressRequest;
use App\Models\Address;
use App\Models\User;
use App\Shipping\Destination;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keeps one rule true at all times: a user with addresses has EXACTLY one
 * primary address. (The database guarantees "at most one"; this class
 * guarantees "at least one".)
 */
class AddressBook
{
    /**
     * @param  array<string, mixed>  $data  validated AddressRequest data
     */
    public function save(User $user, ?Address $address, array $data, ?Destination $destination): Address
    {
        return $this->forUser($user, function () use ($user, $address, $data, $destination): Address {
            if ($address === null && $user->addresses()->count() >= Address::MAX_PER_USER) {
                throw ValidationException::withMessages(['recipient_name' => AddressRequest::limitMessage()]);
            }

            $address ??= $user->addresses()->make();
            $address->fill([
                'label' => $data['label'] ?? null,
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'],
                'street' => $data['street'],
                'notes' => $data['notes'] ?? null,
            ]);

            if ($destination !== null) {
                $address->fill([
                    'destination_id' => $destination->id,
                    'province' => $destination->province,
                    'city' => $destination->city,
                    'district' => $destination->district,
                    'subdistrict' => $destination->subdistrict,
                    'postal_code' => $destination->postalCode,
                ]);
            }

            $first = ! $user->addresses()->whereKeyNot($address->id ?? 0)->exists();
            $wantsPrimary = (bool) ($data['is_primary'] ?? false);

            // Saved as non-primary first; makePrimary() below switches it.
            $address->save();

            if ($first || $wantsPrimary) {
                $this->switchPrimary($user, $address);
            }

            return $address;
        });
    }

    public function makePrimary(User $user, Address $address): void
    {
        $this->forUser($user, fn () => $this->switchPrimary($user, $address));
    }

    /**
     * Deleting the primary address promotes the newest remaining one.
     */
    public function delete(User $user, Address $address): void
    {
        $this->forUser($user, function () use ($user, $address): void {
            $wasPrimary = $address->is_primary;
            $address->delete();

            if ($wasPrimary) {
                $next = $user->addresses()->reorder()->latest('id')->first();
                if ($next !== null) {
                    $this->switchPrimary($user, $next);
                }
            }
        });
    }

    /**
     * ORDER MATTERS: unset the old primary first, then set the new one.
     * The partial unique index is checked per statement, so setting the
     * new one first would briefly mean two primaries: a constraint error.
     */
    private function switchPrimary(User $user, Address $address): void
    {
        $user->addresses()->whereKeyNot($address->id)->where('is_primary', true)->update(['is_primary' => false]);

        $address->is_primary = true;
        $address->save();
    }

    /**
     * Runs one user's address change at a time. Two tabs adding a first
     * address at the same moment would otherwise both make theirs primary,
     * and the second would crash on the unique index.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function forUser(User $user, callable $callback): mixed
    {
        return DB::transaction(function () use ($user, $callback) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            return $callback();
        });
    }
}
