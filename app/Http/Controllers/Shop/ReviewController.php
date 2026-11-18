<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Reviews\WriteReview;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewController extends Controller
{
    public function store(Request $request, OrderItem $item, WriteReview $write): RedirectResponse
    {
        /** @var array{rating: int, body?: string|null} $data */
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ], ['rating.required' => 'Pilih jumlah bintang.'], ['body' => 'ulasan']);

        /** @var User $user */
        $user = $request->user();

        // Someone else's purchase: "not found", like every other foreign id.
        abort_unless($item->order->user_id === $user->id, 404);

        $body = trim((string) ($data['body'] ?? ''));

        $write->handle($user, $item, (int) $data['rating'], $body === '' ? null : $body);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Terima kasih atas ulasannya!']);

        return back();
    }
}
