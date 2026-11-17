<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Addresses\AddressBook;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AddressRequest;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/addresses', [
            'addresses' => $this->user($request)->addresses()->get()->map(fn (Address $a) => [
                'id' => $a->id,
                'label' => $a->label,
                'recipient_name' => $a->recipient_name,
                'phone' => $a->phone,
                'street' => $a->street,
                'notes' => $a->notes,
                'region' => $a->regionLabel(),
                'is_primary' => $a->is_primary,
            ]),
            'maxAddresses' => Address::MAX_PER_USER,
        ]);
    }

    public function store(AddressRequest $request, AddressBook $book): RedirectResponse
    {
        $book->save($this->user($request), null, $request->validated(), $request->destination());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alamat disimpan.']);

        return to_route('addresses.index');
    }

    public function update(AddressRequest $request, int $address, AddressBook $book): RedirectResponse
    {
        $user = $this->user($request);
        $book->save($user, $this->own($user, $address), $request->validated(), $request->destination());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alamat diperbarui.']);

        return to_route('addresses.index');
    }

    public function makePrimary(Request $request, int $address, AddressBook $book): RedirectResponse
    {
        $user = $this->user($request);
        $book->makePrimary($user, $this->own($user, $address));

        return to_route('addresses.index');
    }

    public function destroy(Request $request, int $address, AddressBook $book): RedirectResponse
    {
        $user = $this->user($request);
        $book->delete($user, $this->own($user, $address));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alamat dihapus.']);

        return to_route('addresses.index');
    }

    /** Through the user's own addresses: another user's id is a 404. */
    private function own(User $user, int $id): Address
    {
        return $user->addresses()->findOrFail($id);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
