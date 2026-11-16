<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProfileRequest;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My store" for the logged-in seller. There is no {store} in the URL:
 * a seller can only ever reach their own store, so there is nothing to guess.
 */
class StoreController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $store = $this->user($request)->store;

        if ($store === null) {
            return to_route('seller.store.create');
        }

        return Inertia::render('seller/store/show', [
            'store' => [
                'name' => $store->name,
                'slug' => $store->slug,
                'description' => $store->description,
                'status' => $store->status->value,
                'status_label' => $store->status->label(),
                'approved_at' => $store->approved_at?->toIso8601String(),
            ],
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->user($request)->store()->exists()) {
            return to_route('seller.store.show');
        }

        return Inertia::render('seller/store/create');
    }

    public function store(StoreProfileRequest $request): RedirectResponse
    {
        try {
            // Creating through the relationship fills user_id for us; it is
            // deliberately not fillable, so the form cannot choose an owner.
            $this->user($request)->store()->create($request->validated());
        } catch (UniqueConstraintViolationException $e) {
            // Two possible races between validation and insert:
            // 1. Double submit: the unique index on stores.user_id stopped the
            //    second request. The store exists, so carry on as success.
            // 2. Another seller took the same slug a moment ago: tell the user.
            if ($this->user($request)->store()->doesntExist()) {
                throw ValidationException::withMessages([
                    'slug' => 'Alamat toko ini baru saja dipakai toko lain. Coba variasi lain.',
                ]);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Toko berhasil didaftarkan. Admin akan meninjaunya.']);

        return to_route('seller.store.show');
    }

    public function update(StoreProfileRequest $request): RedirectResponse
    {
        /** @var Store $store authorize() in the request guarantees it exists */
        $store = $this->user($request)->store;

        $store->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profil toko disimpan.']);

        return to_route('seller.store.show');
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
