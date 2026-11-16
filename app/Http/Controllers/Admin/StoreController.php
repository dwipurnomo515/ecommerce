<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Stores\ChangeStoreStatus;
use App\Enums\StoreStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Store::class);

        // Unknown or missing ?status= falls back to the admin's main job:
        // reviewing new stores.
        $status = StoreStatus::tryFrom($request->string('status')->value()) ?? StoreStatus::Pending;

        $stores = Store::query()
            ->with('user:id,name,email')
            ->withCount('products')
            ->where('status', $status)
            ->oldest() // first come, first served
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'description' => $store->description,
                'products_count' => $store->products_count,
                'owner' => ['name' => $store->user->name, 'email' => $store->user->email],
                'created_at' => $store->created_at?->toIso8601String(),
                'approved_at' => $store->approved_at?->toIso8601String(),
            ]);

        /** @var array<string, int> $counts */
        $counts = Store::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return Inertia::render('admin/stores/index', [
            'stores' => $stores,
            'status' => $status->value,
            'tabs' => array_map(fn (StoreStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'count' => (int) ($counts[$s->value] ?? 0),
            ], StoreStatus::cases()),
        ]);
    }

    public function approve(Store $store, ChangeStoreStatus $changeStatus): RedirectResponse
    {
        Gate::authorize('approve', $store);

        return $this->change($store, StoreStatus::Active, $changeStatus, 'disetujui dan sekarang aktif');
    }

    public function suspend(Store $store, ChangeStoreStatus $changeStatus): RedirectResponse
    {
        Gate::authorize('suspend', $store);

        return $this->change($store, StoreStatus::Suspended, $changeStatus, 'ditangguhkan');
    }

    private function change(Store $store, StoreStatus $to, ChangeStoreStatus $changeStatus, string $doneText): RedirectResponse
    {
        try {
            $changeStatus->handle($store, $to);
            Inertia::flash('toast', ['type' => 'success', 'message' => "Toko \"{$store->name}\" {$doneText}."]);
        } catch (InvalidStatusTransition) {
            // Usually another admin acted first. Reload shows the real state.
            Inertia::flash('toast', ['type' => 'error', 'message' => "Status toko \"{$store->name}\" sudah berubah. Halaman dimuat ulang."]);
        }

        return back();
    }
}
