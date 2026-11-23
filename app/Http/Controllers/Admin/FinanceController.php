<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPayout;
use App\Jobs\ProcessRefund;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where the money stands, and what needs a human: payouts and refunds
 * that got stuck or failed.
 */
class FinanceController extends Controller
{
    public function index(): Response
    {
        // Commission lines are negative in the stores' books: Zetago's
        // income is their absolute value.
        $commission = fn ($since = null) => (int) -LedgerEntry::query()
            ->where('type', LedgerEntry::COMMISSION)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->sum('amount');

        return Inertia::render('admin/finance/index', [
            'summary' => [
                'commission_month' => $commission(now()->startOfMonth()),
                'commission_total' => $commission(),
                // Paid by buyers, not yet released to sellers (full amount).
                'escrow' => (int) Order::query()->whereIn('status', Order::HELD_STATUSES)
                    ->sum(DB::raw('items_total + shipping_cost')),
                'seller_balances' => (int) Store::query()->sum('balance'),
                'payouts_in_flight' => (int) Payout::query()->where('status', Payout::PENDING)->sum('amount'),
            ],
            'payouts' => Payout::query()
                ->with('store:id,name')
                ->needsAttention()
                ->orderBy('id')
                ->get()
                ->map(fn (Payout $p) => [
                    'id' => $p->id,
                    'store' => $p->store->name,
                    'amount' => $p->amount,
                    'status' => $p->status,
                    'status_label' => $p->status === Payout::PENDING ? 'Macet' : 'Gagal',
                    'note' => $p->failure_reason,
                    'created_at' => $p->created_at?->toIso8601String(),
                    // A failed payout already returned the money to the
                    // seller: nothing to retry, it's here for information.
                    'retryable' => $p->status === Payout::PENDING,
                ]),
            'refunds' => Refund::query()
                ->with('checkout:id,code')
                ->needsAttention()
                ->orderBy('id')
                ->get()
                ->map(fn (Refund $r) => [
                    'id' => $r->id,
                    'checkout' => $r->checkout->code,
                    'amount' => $r->amount,
                    'status' => $r->status,
                    'status_label' => $r->status === Refund::PENDING ? 'Macet' : 'Gagal',
                    'note' => $r->failure_reason ?? $r->reason,
                    'created_at' => $r->created_at?->toIso8601String(),
                    // Failed or stuck: the buyer is still owed. Always retryable.
                    'retryable' => true,
                ]),
        ]);
    }

    /**
     * Sends a stuck payout through the job again. Safe: the job re-checks
     * "still pending" on a locked row, and a real provider gets the payout
     * id as idempotency key, so a transfer that did go through isn't sent
     * twice.
     */
    public function retryPayout(Payout $payout): RedirectResponse
    {
        if ($payout->status !== Payout::PENDING) {
            return $this->done('Penarikan ini sudah tidak menunggu.', 'error');
        }

        AuditLog::record('payout.retried', $payout, "Payout #{$payout->id}", ['amount' => $payout->amount]);
        ProcessPayout::dispatch($payout->id);

        return $this->done("Penarikan #{$payout->id} dicoba ulang.");
    }

    /** A failed or stuck refund: the buyer is still owed. Try again. */
    public function retryRefund(Refund $refund): RedirectResponse
    {
        if ($refund->status === Refund::REFUNDED) {
            return $this->done('Refund ini sudah berhasil.', 'error');
        }

        DB::transaction(function () use ($refund): void {
            $refund->forceFill(['status' => Refund::PENDING, 'failure_reason' => null])->save();
            AuditLog::record('refund.retried', $refund, "Refund #{$refund->id}", ['amount' => $refund->amount]);
        });
        ProcessRefund::dispatch($refund->id);

        return $this->done("Refund #{$refund->id} dicoba ulang.");
    }

    private function done(string $message, string $type = 'success'): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
