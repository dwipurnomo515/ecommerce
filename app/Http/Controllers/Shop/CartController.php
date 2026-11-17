<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ChangeCartQuantity;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\User;
use App\Support\CartView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('shop/cart', [
            'cart' => CartView::for($this->user($request)),
            'maxQuantity' => CartItem::MAX_QUANTITY,
        ]);
    }

    public function update(Request $request, int $item, ChangeCartQuantity $change): RedirectResponse
    {
        /** @var array{quantity: int} $data */
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartItem::MAX_QUANTITY],
        ], attributes: ['quantity' => 'jumlah']);

        $change->handle($this->ownItem($request, $item), (int) $data['quantity']);

        return back();
    }

    public function destroy(Request $request, int $item): RedirectResponse
    {
        $this->ownItem($request, $item)->delete();

        return back();
    }

    /**
     * Looked up THROUGH the user's own cart: someone else's item id is
     * simply "not found" (404), never deleted. No policy class needed.
     */
    private function ownItem(Request $request, int $id): CartItem
    {
        return $this->user($request)->cartItems()->findOrFail($id);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

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
