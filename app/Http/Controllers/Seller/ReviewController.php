<?php

namespace App\Http\Controllers\Seller;

use App\Actions\Reviews\ReplyToReview;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $store = $user->store;

        if ($store === null) {
            return to_route('seller.store.create');
        }

        $unanswered = $request->string('filter')->value() !== 'semua';

        $reviews = Review::query()
            ->where('store_id', $store->id)
            // A hidden review needs no answer: it isn't shown to anyone.
            ->when($unanswered, fn ($q) => $q->whereNull('seller_reply')->whereNull('hidden_at'))
            ->with(['user:id,name', 'product:id,name'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Review $r) => [
                'id' => $r->id,
                'product' => $r->product->name,
                'variant' => $r->variant_label,
                // Same short name buyers see publicly.
                'author' => Review::publicName($r->user->name),
                'rating' => $r->rating,
                'body' => $r->body,
                'created_at' => $r->created_at?->toIso8601String(),
                'seller_reply' => $r->seller_reply,
                'replied_at' => $r->replied_at?->toIso8601String(),
                // The seller sees that (and why) a review was taken down.
                'hidden_reason' => $r->hidden_at !== null ? $r->hidden_reason : null,
            ]);

        return Inertia::render('seller/reviews/index', [
            'reviews' => $reviews,
            'filter' => $unanswered ? 'belum-dibalas' : 'semua',
            'unansweredCount' => Review::query()->where('store_id', $store->id)->whereNull('seller_reply')->whereNull('hidden_at')->count(),
            'rating' => $store->ratingSummary(),
        ]);
    }

    public function reply(Request $request, Review $review, ReplyToReview $replyTo): RedirectResponse
    {
        Gate::authorize('reply', $review);

        /** @var array{reply: string} $data */
        $data = $request->validate(
            ['reply' => ['required', 'string', 'min:2', 'max:1000']],
            attributes: ['reply' => 'balasan'],
        );

        $replyTo->handle($review, trim($data['reply']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Balasan dikirim.']);

        return back();
    }
}
