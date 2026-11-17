<?php

namespace App\Http\Controllers\Shop;

use App\Checkout\CheckoutPreview;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    /**
     * GET /checkout?alamat={id}: preview with the chosen (or primary)
     * address. Changing the address is a new GET, so the server re-quotes
     * shipping for it.
     */
    public function show(Request $request, CheckoutPreview $preview): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $addresses = $user->addresses()->get();

        $address = $request->filled('alamat')
            // Through the user's own addresses: someone else's id is a 404.
            ? $user->addresses()->findOrFail($request->integer('alamat'))
            : $addresses->firstWhere('is_primary', true);

        $checkout = $preview->build($user, $address);

        if ($checkout['stores'] === []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Tidak ada barang di keranjang yang bisa dibeli saat ini.']);

            return to_route('cart.index');
        }

        return Inertia::render('shop/checkout', [
            'checkout' => $checkout,
            'addresses' => $addresses->map(fn (Address $a) => [
                'id' => $a->id,
                'label' => $a->label,
                'recipient_name' => $a->recipient_name,
                'phone' => $a->phone,
                'street' => $a->street,
                'region' => $a->regionLabel(),
                'is_primary' => $a->is_primary,
            ]),
            'addressId' => $address?->id,
        ]);
    }
}
