<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Store;
use App\Models\User;
use App\Payouts\RequestPayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Saldo": what the store has, what is still on its way (escrow), and the
 * statement that explains every Rupiah.
 */
class BalanceController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $store = $user->store;

        if ($store === null) {
            return to_route('seller.store.create');
        }

        // Paid but not completed yet: money the store will receive once the
        // buyer confirms. Same formula as Order::sellerEarning(); Postgres
        // integer division also rounds the commission down.
        $held = (int) Order::query()
            ->where('store_id', $store->id)
            ->whereIn('status', Order::HELD_STATUSES)
            ->sum(DB::raw(Order::SELLER_EARNING_SQL));

        $entries = LedgerEntry::query()
            ->where('store_id', $store->id)
            ->orderByDesc('id')
            ->paginate(30)
            ->through(fn (LedgerEntry $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'description' => $e->description,
                'amount' => $e->amount,
                'balance_after' => $e->balance_after,
                'created_at' => $e->created_at->toIso8601String(),
            ]);

        return Inertia::render('seller/balance/index', [
            'balance' => $store->balance,
            'held' => $held,
            'commissionPercent' => config()->integer('marketplace.commission_bps') / 100,
            'entries' => $entries,
            'account' => $store->payout_account_number === null ? null : [
                'bank' => $store->payout_bank,
                'bank_name' => Payout::BANKS[$store->payout_bank] ?? $store->payout_bank,
                'number' => $store->payout_account_number,
                'masked' => RequestPayout::masked($store->payout_account_number),
                'name' => $store->payout_account_name,
            ],
            'banks' => collect(Payout::BANKS)->map(fn (string $name, string $code) => ['code' => $code, 'name' => $name])->values(),
            'minimum' => Payout::MINIMUM,
            'payouts' => Payout::query()->where('store_id', $store->id)->latest('id')->limit(10)->get()
                ->map(fn (Payout $p) => [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'status' => $p->status,
                    'status_label' => $p->label(),
                    'destination' => (Payout::BANKS[$p->bank] ?? $p->bank).' '.RequestPayout::masked($p->account_number),
                    'reference' => $p->reference,
                    'failure_reason' => $p->failure_reason,
                    'created_at' => $p->created_at?->toIso8601String(),
                ]),
            // Honest about it: no real money moves yet.
            'simulated' => true,
        ]);
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $store = $this->store($request);

        /** @var array{bank: string, account_number: string, account_name: string} $data */
        $data = $request->validate([
            'bank' => ['required', Rule::in(array_keys(Payout::BANKS))],
            'account_number' => ['required', 'regex:/^[0-9]{6,20}$/'],
            'account_name' => ['required', 'string', 'min:3', 'max:100'],
        ], ['account_number.regex' => 'Nomor rekening hanya angka, 6–20 digit.'], [
            'bank' => 'bank', 'account_number' => 'nomor rekening', 'account_name' => 'nama pemilik rekening',
        ]);

        $describe = fn (?string $bank, ?string $number, ?string $name) => $number === null
            ? null
            : (Payout::BANKS[$bank] ?? $bank).' '.RequestPayout::masked($number)." a.n. {$name}";

        DB::transaction(function () use ($store, $data, $describe): void {
            $before = $describe($store->payout_bank, $store->payout_account_number, $store->payout_account_name);

            // Not fillable on purpose: set here, from validated input only.
            $store->forceFill([
                'payout_bank' => $data['bank'],
                'payout_account_number' => $data['account_number'],
                'payout_account_name' => trim($data['account_name']),
            ])->save();

            // A changed payout account is THE classic step of an account
            // takeover ("send the money to me instead"): always on record.
            AuditLog::record('store.bank_account_changed', $store, $store->name, [
                'from' => $before,
                'to' => $describe($store->payout_bank, $store->payout_account_number, $store->payout_account_name),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rekening disimpan.']);

        return back();
    }

    public function withdraw(Request $request, RequestPayout $requestPayout): RedirectResponse
    {
        /** @var array{amount: int} $data */
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1']], attributes: ['amount' => 'jumlah']);

        $requestPayout->handle($this->store($request), (int) $data['amount']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penarikan diajukan dan sedang diproses.']);

        return back();
    }

    private function store(Request $request): Store
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->store === null, 404);

        return $user->store;
    }
}
