<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reviews\ModerateReview;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    private const FILTERS = [
        'rendah' => 'Bintang 1–2',
        'semua' => 'Semua',
        'disembunyikan' => 'Disembunyikan',
    ];

    public function index(Request $request): Response
    {
        // Default: low ratings, where abuse and disputes usually are.
        $filter = $request->string('filter')->value();
        $filter = array_key_exists($filter, self::FILTERS) ? $filter : 'rendah';

        $reviews = Review::query()
            // store_id is needed to load product.store (strict mode would
            // refuse the missing column rather than silently return null).
            ->with(['user:id,name', 'product:id,name,store_id', 'product.store:id,name'])
            ->when($filter === 'rendah', fn ($q) => $q->where('rating', '<=', 2)->whereNull('hidden_at'))
            ->when($filter === 'disembunyikan', fn ($q) => $q->whereNotNull('hidden_at'))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Review $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'body' => $r->body,
                'author' => $r->user->name, // admins see the full name
                'product' => $r->product->name,
                'store' => $r->product->store->name,
                'created_at' => $r->created_at?->toIso8601String(),
                'hidden_reason' => $r->hidden_reason,
                'hidden' => $r->hidden_at !== null,
            ]);

        return Inertia::render('admin/reviews/index', [
            'reviews' => $reviews,
            'filter' => $filter,
            'filters' => collect(self::FILTERS)->map(fn (string $label, string $value) => compact('value', 'label'))->values(),
        ]);
    }

    public function hide(Request $request, Review $review, ModerateReview $moderate): RedirectResponse
    {
        /** @var array{reason: string} $data */
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']], attributes: ['reason' => 'alasan']);

        $moderate->hide($review, trim($data['reason']));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ulasan disembunyikan dan tidak lagi dihitung di rating.']);

        return back();
    }

    public function show(Review $review, ModerateReview $moderate): RedirectResponse
    {
        $moderate->show($review);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ulasan ditampilkan kembali.']);

        return back();
    }
}
