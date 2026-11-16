<?php

namespace App\Actions\Stores;

use App\Enums\StoreStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Business rule: "an admin approves / suspends a store".
 *
 * Kept out of the controller so the same rule can be reused later from
 * a console command, a queued job or an API without copying it.
 */
class ChangeStoreStatus
{
    /**
     * @throws InvalidStatusTransition
     */
    public function handle(Store $store, StoreStatus $to): Store
    {
        return DB::transaction(function () use ($store, $to): Store {
            // Re-read the row WITH a lock: if two admins click at the same
            // moment, the second one waits here and then sees the new status,
            // instead of both acting on the stale value they loaded earlier.
            $locked = Store::query()->lockForUpdate()->findOrFail($store->id);

            if (! $locked->status->canTransitionTo($to)) {
                throw InvalidStatusTransition::from($locked->status, $to);
            }

            $locked->status = $to;

            // Remember when the store was FIRST approved; reactivating a
            // suspended store keeps the original date.
            if ($to === StoreStatus::Active && $locked->approved_at === null) {
                $locked->approved_at = now();
            }

            $locked->save();

            return $locked;
        });
    }
}
