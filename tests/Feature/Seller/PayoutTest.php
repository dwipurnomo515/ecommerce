<?php

namespace Tests\Feature\Seller;

use App\Jobs\ProcessPayout;
use App\Ledger\Ledger;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Store;
use App\Payouts\PayoutGateway;
use App\Payouts\PayoutUnavailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->active()->create();
        $this->store->forceFill([
            'payout_bank' => 'bca',
            'payout_account_number' => '1234567890',
            'payout_account_name' => 'Penjual Contoh',
        ])->save();

        // Rp 200.000 earned (as if from completed orders).
        app(Ledger::class)->post($this->store->id, null, 'adjustment', 200_000, 'Saldo awal (uji)');
    }

    private function withdraw(int $amount): TestResponse
    {
        return $this->actingAs($this->store->user->fresh() ?? $this->store->user)
            ->post(route('seller.balance.withdraw'), ['amount' => $amount]);
    }

    private function balance(): int
    {
        return (int) $this->store->fresh()?->balance;
    }

    private function runJob(Payout $payout): void
    {
        app()->call([new ProcessPayout($payout->id), 'handle']);
    }

    public function test_a_withdrawal_debits_the_balance_at_once_and_queues_the_transfer(): void
    {
        Queue::fake();

        $this->withdraw(150_000)->assertSessionHasNoErrors();

        $payout = Payout::query()->sole();
        $this->assertSame([Payout::PENDING, 150_000, '1234567890'], [$payout->status, $payout->amount, $payout->account_number]);
        $this->assertSame(50_000, $this->balance(), 'Debited before the transfer: no double spending.');
        $this->assertDatabaseHas('store_ledger_entries', ['payout_id' => $payout->id, 'type' => 'payout', 'amount' => -150_000]);

        Queue::assertPushed(ProcessPayout::class, fn (ProcessPayout $job) => $job->payoutId === $payout->id);
    }

    public function test_a_successful_transfer_marks_the_payout_paid(): void
    {
        Queue::fake();
        $this->withdraw(150_000);
        $payout = Payout::query()->sole();

        $this->runJob($payout);

        $payout->refresh();
        $this->assertSame(Payout::PAID, $payout->status);
        $this->assertSame(sprintf('SIM-%08d', $payout->id), $payout->reference);
        $this->assertSame(50_000, $this->balance());
    }

    public function test_a_failed_transfer_gives_the_money_back_as_a_new_entry(): void
    {
        Queue::fake();
        $this->store->forceFill(['payout_account_number' => '9876540000'])->save(); // simulated "no such account"
        $this->withdraw(150_000);
        $payout = Payout::query()->sole();

        $this->runJob($payout);
        $this->runJob($payout); // a duplicate job must not pay back twice

        $payout->refresh();
        $this->assertSame(Payout::FAILED, $payout->status);
        $this->assertSame('Rekening tidak ditemukan (simulasi).', $payout->failure_reason);
        $this->assertSame(200_000, $this->balance());
        $this->assertSame([200_000, -150_000, 150_000], LedgerEntry::query()->orderBy('id')->pluck('amount')->all(),
            'History keeps the withdrawal AND its reversal.');
    }

    public function test_a_temporary_outage_leaves_the_payout_pending_for_a_retry(): void
    {
        Queue::fake();
        $this->store->forceFill(['payout_account_number' => '5555559999'])->save(); // simulated outage
        $this->withdraw(100_000);
        $payout = Payout::query()->sole();

        try {
            $this->runJob($payout);
            $this->fail('The job must throw so the queue retries it.');
        } catch (PayoutUnavailable) {
            // expected
        }

        $this->assertSame(Payout::PENDING, $payout->fresh()?->status);
        $this->assertSame(100_000, $this->balance(), 'Money stays reserved while retrying.');
        $this->assertSame(5, (new ProcessPayout($payout->id))->tries);
    }

    public function test_the_rules_minimum_balance_and_one_at_a_time(): void
    {
        Queue::fake();

        $this->withdraw(Payout::MINIMUM - 1)->assertSessionHasErrors(['amount' => 'Penarikan minimal Rp 50.000.']);
        $this->withdraw(200_001)->assertSessionHasErrors(['amount' => 'Saldo tidak cukup.']);

        $this->withdraw(60_000)->assertSessionHasNoErrors();
        $this->withdraw(60_000)->assertSessionHasErrors(['amount' => 'Masih ada penarikan yang sedang diproses. Tunggu sampai selesai.']);

        $this->assertSame(1, Payout::query()->count());
        $this->assertSame(140_000, $this->balance());
    }

    public function test_changing_the_account_later_does_not_redirect_a_pending_transfer(): void
    {
        Queue::fake();
        $this->withdraw(100_000);

        $this->actingAs($this->store->user)->put(route('seller.balance.account'), [
            'bank' => 'bni', 'account_number' => '1111111111', 'account_name' => 'Orang Lain',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['bca', '1234567890'], [Payout::query()->sole()->bank, Payout::query()->sole()->account_number]);
    }

    public function test_bank_account_input_is_validated(): void
    {
        foreach ([
            ['bank' => 'bank-palsu', 'account_number' => '1234567890', 'account_name' => 'Nama'],
            ['bank' => 'bca', 'account_number' => '12-34', 'account_name' => 'Nama'],
            ['bank' => 'bca', 'account_number' => '1234567890', 'account_name' => ''],
        ] as $bad) {
            $this->actingAs($this->store->user)->put(route('seller.balance.account'), $bad)->assertSessionHasErrors();
        }
    }

    public function test_the_full_flow_runs_through_the_queue(): void
    {
        // phpunit.xml uses the sync queue: the job runs right after commit.
        $this->withdraw(120_000)->assertSessionHasNoErrors();

        $this->assertSame(Payout::PAID, Payout::query()->sole()->status);
        $this->actingAs($this->store->user->fresh() ?? $this->store->user)
            ->get(route('seller.balance.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('balance', 80_000)
                ->where('payouts.0.status', 'paid')
                ->where('payouts.0.destination', 'BCA ••••7890')
                ->where('simulated', true));
    }

    public function test_the_simulation_is_refused_in_production(): void
    {
        $this->app->forgetInstance(PayoutGateway::class);
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        app(PayoutGateway::class);
    }
}
