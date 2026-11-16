<?php

namespace Tests\Feature\Seller;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpenStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_unverified_users_cannot_open_a_store(): void
    {
        $this->get(route('seller.store.create'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('seller.store.create'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_my_store_sends_users_without_a_store_to_the_open_store_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('seller.store.show'))
            ->assertRedirect(route('seller.store.create'));
    }

    public function test_a_user_opens_a_store_that_waits_for_approval(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('seller.store.store'), [
                'name' => 'Toko Budi Elektronik',
                'slug' => '',
                'description' => 'Servis dan jual beli HP bekas.',
            ])
            ->assertRedirect(route('seller.store.show'))
            ->assertInertiaFlash('toast.type', 'success');

        $store = $user->store()->firstOrFail();
        $this->assertSame('toko-budi-elektronik', $store->slug);
        $this->assertSame(StoreStatus::Pending, $store->status);
        $this->assertNull($store->approved_at);
    }

    public function test_the_form_cannot_approve_the_store_or_pick_another_owner(): void
    {
        $user = User::factory()->create();
        $someoneElse = User::factory()->create();

        $this->actingAs($user)->post(route('seller.store.store'), [
            'name' => 'Toko Curang',
            'status' => 'active',
            'user_id' => $someoneElse->id,
        ]);

        $store = Store::query()->sole();
        $this->assertSame($user->id, $store->user_id);
        $this->assertSame(StoreStatus::Pending, $store->status);
    }

    public function test_a_user_can_only_open_one_store(): void
    {
        $store = Store::factory()->create();

        $this->actingAs($store->user)
            ->get(route('seller.store.create'))
            ->assertRedirect(route('seller.store.show'));

        $this->actingAs($store->user)
            ->post(route('seller.store.store'), ['name' => 'Toko Kedua'])
            ->assertForbidden();

        $this->assertDatabaseCount('stores', 1);
    }

    public function test_store_addresses_are_unique(): void
    {
        Store::factory()->create(['slug' => 'toko-budi']);

        $this->actingAs(User::factory()->create())
            ->post(route('seller.store.store'), ['name' => 'Toko Budi'])
            ->assertSessionHasErrors('slug');
    }

    public function test_the_store_page_shows_its_review_status(): void
    {
        $store = Store::factory()->create(['name' => 'Toko Sari']);

        $this->actingAs($store->user)
            ->get(route('seller.store.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('seller/store/show')
                ->where('store.name', 'Toko Sari')
                ->where('store.status', 'pending')
                ->where('store.status_label', 'Menunggu persetujuan'));
    }

    public function test_a_pending_store_can_still_change_its_address(): void
    {
        $store = Store::factory()->create(['slug' => 'toko-lama']);

        $this->actingAs($store->user)
            ->put(route('seller.store.update'), ['name' => 'Toko Baru', 'slug' => 'Toko Baru!'])
            ->assertSessionHasNoErrors();

        $this->assertSame('toko-baru', $store->fresh()?->slug);
    }

    public function test_an_approved_store_keeps_its_address_but_can_update_its_profile(): void
    {
        $store = Store::factory()->active()->create(['slug' => 'toko-tetap']);

        $this->actingAs($store->user)
            ->put(route('seller.store.update'), [
                'name' => 'Nama Baru',
                'slug' => 'alamat-baru',
                'description' => 'Deskripsi baru',
            ])
            ->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame('toko-tetap', $store->slug);
        $this->assertSame('Nama Baru', $store->name);
        $this->assertSame('Deskripsi baru', $store->description);
    }

    public function test_users_without_a_store_cannot_update_one(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('seller.store.update'), ['name' => 'Apa saja'])
            ->assertForbidden();
    }
}
