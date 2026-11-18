<?php

namespace Tests\Feature\Admin;

use App\Jobs\ProcessPayout;
use App\Jobs\ProcessRefund;
use App\Ledger\Ledger;
use App\Models\AuditLog;
use App\Models\Checkout;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\Store;
use App\Models\User;
use App\Payouts\RequestPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->store = Store::factory()->active()->create();
        $this->store->forceFill(['payout_bank' => 'bca', 'payout_account_number' => '1234567890', 'payout_account_name' => 'Budi'])->save();

        $ledger = app(Ledger::class);
        $ledger->post($this->store->id, null, LedgerEntry::ORDER_INCOME, 200_000, 'Pesanan uji');
        $ledger->post($this->store->id, null, LedgerEntry::COMMISSION, -10_000, 'Komisi uji');
    }

    private function page(): TestResponse
    {
        return $this->actingAs($this->admin)->get(route('admin.finance.index'))->assertOk();
    }

    private function pendingPayout(): Payout
    {
        Queue::fake(); // the job doesn't run: the payout stays pending

        return app(RequestPayout::class)->handle($this->store, 100_000);
    }

    private function refund(string $status): Refund
    {
        $checkout = new Checkout;
        $checkout->forceFill([
            'user_id' => User::factory()->create()->id, 'code' => 'ZTG-REFUNDUJI', 'idempotency_key' => fake()->uuid(),
            'payment_status' => 'needs_refund', 'items_total' => 50_000, 'shipping_total' => 0,
            'grand_total' => 50_000, 'expires_at' => now(),
        ])->save();

        $refund = new Refund;
        $refund->forceFill(['checkout_id' => $checkout->id, 'amount' => 50_000, 'reason' => 'Uji',
            'status' => $status, 'failure_reason' => $status === Refund::FAILED ? 'Bank menolak' : null])->save();

        return $refund;
    }

    public function test_the_summary_shows_commission_and_seller_balances(): void
    {
        $this->page()->assertInertia(fn (Assert $p) => $p
            ->component('admin/finance/index')
            ->where('summary.commission_total', 10_000)
            ->where('summary.commission_month', 10_000)
            ->where('summary.seller_balances', 190_000)
            ->where('summary.escrow', 0));
    }

    public function test_only_payouts_pending_for_over_an_hour_count_as_stuck(): void
    {
        $this->pendingPayout();

        $this->page()->assertInertia(fn (Assert $p) => $p->has('payouts', 0));

        $this->travel(61)->minutes();

        $this->page()->assertInertia(fn (Assert $p) => $p
            ->has('payouts', 1)
            ->where('payouts.0.status_label', 'Macet')
            ->where('payouts.0.retryable', true));
    }

    public function test_retrying_a_stuck_payout_queues_it_again_and_is_audited(): void
    {
        $payout = $this->pendingPayout();
        $this->travel(2)->hours();

        $this->actingAs($this->admin)->post(route('admin.finance.payouts.retry', $payout))->assertSessionHasNoErrors();

        Queue::assertPushed(ProcessPayout::class, fn (ProcessPayout $job) => $job->payoutId === $payout->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout.retried', 'actor_id' => $this->admin->id]);

        // The job itself is idempotent: running it settles the payout once.
        app()->call([new ProcessPayout($payout->id), 'handle']);
        $this->assertSame(Payout::PAID, $payout->fresh()?->status);
    }

    public function test_a_failed_refund_can_be_retried_until_the_buyer_is_paid(): void
    {
        $refund = $this->refund(Refund::FAILED);

        $this->page()->assertInertia(fn (Assert $p) => $p->where('refunds.0.status_label', 'Gagal'));

        // Sync queue in tests: the job runs right away and succeeds.
        $this->actingAs($this->admin)->post(route('admin.finance.refunds.retry', $refund));

        $this->assertSame(Refund::REFUNDED, $refund->fresh()?->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'refund.retried')->count());
        $this->page()->assertInertia(fn (Assert $p) => $p->has('refunds', 0));
    }

    public function test_a_finished_refund_is_not_retried(): void
    {
        Queue::fake();
        $refund = $this->refund(Refund::REFUNDED);

        $this->actingAs($this->admin)->post(route('admin.finance.refunds.retry', $refund));

        Queue::assertNotPushed(ProcessRefund::class);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_only_admins_reach_the_finance_page(): void
    {
        $this->actingAs($this->store->user)->get(route('admin.finance.index'))->assertForbidden();
        $this->actingAs($this->store->user)
            ->post(route('admin.finance.refunds.retry', $this->refund(Refund::FAILED)))
            ->assertForbidden();
    }
}
