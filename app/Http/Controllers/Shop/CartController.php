<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Cart\AddToCart;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartController extends Controller
{
    public function store(Request $request, AddToCart $addToCart): RedirectResponse
    {
        /** @var array{variant_id: int, quantity: int} $data */
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartItem::MAX_QUANTITY],
        ], attributes: ['quantity' => 'jumlah']);

        /** @var User $user */
        $user = $request->user();

        $addToCart->handle($user, (int) $data['variant_id'], (int) $data['quantity']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ditambahkan ke keranjang.']);

        return back();
    }

    /**
     * Guests clicking "Tambah ke keranjang" come here. The auth middleware
     * remembers this URL, sends them to log in, and brings them back here
     * afterwards; we then return them to the product.
     *
     * The target is built from the route parameters, never taken from a
     * "?redirect=" query string: that would be an open redirect (a link
     * on our domain that bounces users to a phishing site).
     */
    public function loginToBuy(string $storeSlug, string $productSlug): RedirectResponse
    {
        return redirect()->route('shop.products.show', [$storeSlug, $productSlug]);
    }
}
