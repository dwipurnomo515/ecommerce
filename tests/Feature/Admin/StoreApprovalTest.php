<?php

namespace Tests\Feature\Admin;

use App\Actions\Stores\ChangeStoreStatus;
use App\Enums\StoreStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_regular_users_cannot_review_or_approve_stores(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create();

        $this->actingAs($user)->get(route('admin.stores.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.stores.approve', $store))->assertForbidden();

        $this->assertSame(StoreStatus::Pending, $store->fresh()?->status);
    }

    public function test_the_list_shows_pending_stores_first_with_counts_per_status(): void
    {
        Store::factory()->count(2)->create();
        Store::factory()->active()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.stores.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/stores/index')
                ->where('status', 'pending')
                ->has('stores.data', 2)
                ->has('stores.data.0.owner.email')
                ->where('tabs.0.count', 2)
                ->where('tabs.1.count', 1)
                ->where('tabs.2.count', 0));
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        Store::factory()->create();
        $active = Store::factory()->active()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.stores.index', ['status' => 'active']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'active')
                ->has('stores.data', 1)
                ->where('stores.data.0.id', $active->id));
    }

    public function test_an_admin_approves_a_pending_store(): void
    {
        // freezeSecond, not freezeTime: the DB column stores whole seconds,
        // so a frozen time with microseconds would never compare equal.
        $this->freezeSecond();
        $store = Store::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.stores.approve', $store))
            ->assertInertiaFlash('toast.type', 'success');

        $store->refresh();
        $this->assertSame(StoreStatus::Active, $store->status);
        $this->assertTrue($store->approved_at?->equalTo(now()));
    }

    public function test_reactivating_a_suspended_store_keeps_the_first_approval_date(): void
    {
        $store = Store::factory()->suspended()->create();
        $firstApproval = $store->approved_at;

        $this->actingAs($this->admin)->post(route('admin.stores.approve', $store));

        $store->refresh();
        $this->assertSame(StoreStatus::Active, $store->status);
        $this->assertTrue($store->approved_at?->equalTo($firstApproval));
    }

    public function test_an_admin_suspends_an_active_store(): void
    {
        $store = Store::factory()->active()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.stores.suspend', $store))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertSame(StoreStatus::Suspended, $store->fresh()?->status);
    }

    public function test_approving_an_already_active_store_is_refused_politely(): void
    {
        // e.g. two admins clicked "Setujui" on the same store
        $store = Store::factory()->active()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.stores.approve', $store))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'error');
    }

    public function test_the_action_uses_the_current_database_status_not_a_stale_copy(): void
    {
        $staleCopy = Store::factory()->create(); // loaded while still pending
        Store::query()->whereKey($staleCopy->id)->update(['status' => 'active']); // another admin approved

        $this->expectException(InvalidStatusTransition::class);

        app(ChangeStoreStatus::class)->handle($staleCopy, StoreStatus::Active);
    }

    public function test_allowed_status_transitions(): void
    {
        $this->assertTrue(StoreStatus::Pending->canTransitionTo(StoreStatus::Active));
        $this->assertTrue(StoreStatus::Pending->canTransitionTo(StoreStatus::Suspended));
        $this->assertTrue(StoreStatus::Active->canTransitionTo(StoreStatus::Suspended));
        $this->assertTrue(StoreStatus::Suspended->canTransitionTo(StoreStatus::Active));

        $this->assertFalse(StoreStatus::Active->canTransitionTo(StoreStatus::Active));
        $this->assertFalse(StoreStatus::Active->canTransitionTo(StoreStatus::Pending));
        $this->assertFalse(StoreStatus::Suspended->canTransitionTo(StoreStatus::Pending));
    }
}
