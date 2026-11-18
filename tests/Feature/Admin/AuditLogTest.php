<?php

namespace Tests\Feature\Admin;

use App\Jobs\ProcessPayout;
use App\Ledger\Ledger;
use App\Models\AuditLog;
use App\Models\Payout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Admin Satu']);
    }

    private function product(): Product
    {
        $product = Product::factory()->active()->for(Store::factory()->active())->create(['name' => 'Kaos Polos']);
        ProductVariant::factory()->for($product)->create();

        return $product;
    }

    public function test_a_ban_and_its_lifting_are_logged_and_the_old_reason_survives(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin)->post(route('admin.products.ban', $product), ['reason' => 'Foto memakai merek palsu.']);
        $this->actingAs($this->admin)->post(route('admin.products.unban', $product));

        $logs = AuditLog::query()->orderBy('id')->get();
        $this->assertSame(['product.banned', 'product.unbanned'], $logs->pluck('action')->all());
        $this->assertSame($this->admin->id, $logs[0]->actor_id);
        $this->assertSame('Kaos Polos', $logs[0]->subject_label);
        $this->assertSame('127.0.0.1', $logs[0]->ip);
        // The product no longer holds the reason; the log still does.
        $this->assertNull($product->fresh()?->ban_reason);
        $this->assertSame('Foto memakai merek palsu.', $logs[1]->details['previous_reason'] ?? null);
    }

    public function test_a_refused_action_leaves_no_log_line(): void
    {
        $product = $this->product();
        $this->actingAs($this->admin)->post(route('admin.products.ban', $product), ['reason' => 'Alasan pertama yang jelas.']);

        // Banning again fails (already banned): rolled back, so not logged.
        $this->actingAs($this->admin)->post(route('admin.products.ban', $product), ['reason' => 'Alasan kedua yang jelas.']);

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_store_approval_is_logged(): void
    {
        $store = Store::factory()->create(['name' => 'Toko Baru']);

        $this->actingAs($this->admin)->post(route('admin.stores.approve', $store));

        $log = AuditLog::query()->sole();
        $this->assertSame(['store.approved', 'Toko Baru'], [$log->action, $log->subject_label]);
        // assertEquals: jsonb reorders object keys, so key order is not kept.
        $this->assertEquals(['from' => 'pending', 'to' => 'active'], $log->details);
    }

    public function test_bank_account_changes_are_logged_without_full_numbers(): void
    {
        $store = Store::factory()->active()->create();

        $this->actingAs($store->user)->put(route('seller.balance.account'), [
            'bank' => 'bca', 'account_number' => '1234567890', 'account_name' => 'Budi',
        ]);
        $this->actingAs($store->user)->put(route('seller.balance.account'), [
            'bank' => 'bni', 'account_number' => '9876543210', 'account_name' => 'Orang Lain',
        ]);

        $last = AuditLog::query()->latest('id')->firstOrFail();
        $this->assertSame('store.bank_account_changed', $last->action);
        $this->assertEquals(['from' => 'BCA ••••7890 a.n. Budi', 'to' => 'BNI ••••3210 a.n. Orang Lain'], $last->details);
        $this->assertStringNotContainsString('9876543210', (string) json_encode($last->details));
        $this->assertSame($store->user_id, $last->actor_id);
    }

    public function test_payouts_are_logged_and_the_queue_job_acts_as_the_system(): void
    {
        Queue::fake();
        $store = Store::factory()->active()->create();
        $store->forceFill(['payout_bank' => 'bca', 'payout_account_number' => '1234567890', 'payout_account_name' => 'Budi'])->save();
        app(Ledger::class)->post($store->id, null, 'adjustment', 100_000, 'Uji');

        $this->actingAs($store->user->fresh() ?? $store->user)->post(route('seller.balance.withdraw'), ['amount' => 60_000]);
        auth()->logout(); // the queue worker has no logged-in user
        app()->call([new ProcessPayout(Payout::query()->sole()->id), 'handle']);

        $logs = AuditLog::query()->orderBy('id')->get();
        $this->assertSame(['payout.requested', 'payout.paid'], $logs->pluck('action')->all());
        $this->assertSame($store->user_id, $logs[0]->actor_id);
        $this->assertNull($logs[1]->actor_id, 'System.');
        $this->assertSame('BCA ••••7890', $logs[0]->details['to'] ?? null);
    }

    public function test_granting_admin_rights_from_the_console_is_logged(): void
    {
        $user = User::factory()->create(['email' => 'calon@example.com']);

        $this->artisan('app:make-admin', ['email' => 'calon@example.com'])->assertSuccessful();

        $log = AuditLog::query()->sole();
        $this->assertSame(['user.admin_granted', $user->id, null], [$log->action, $log->subject_id, $log->actor_id]);
    }

    public function test_log_lines_cannot_be_edited_or_deleted(): void
    {
        $this->actingAs($this->admin)->post(route('admin.stores.approve', Store::factory()->create()));
        $log = AuditLog::query()->sole();

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_only_admins_see_the_audit_page(): void
    {
        $this->actingAs($this->admin)->post(route('admin.stores.approve', Store::factory()->create()));

        $this->actingAs($this->admin)->get(route('admin.audit.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('admin/audit/index')
                ->where('logs.data.0.label', 'Menyetujui toko')
                ->where('logs.data.0.actor', 'Admin Satu ('.$this->admin->email.')'));

        $this->actingAs(User::factory()->create())->get(route('admin.audit.index'))->assertForbidden();
    }
}
