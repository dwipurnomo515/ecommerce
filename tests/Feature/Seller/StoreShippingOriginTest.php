<?php

namespace Tests\Feature\Seller;

use App\Models\Store;
use App\Models\User;
use App\Shipping\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreShippingOriginTest extends TestCase
{
    use RefreshDatabase;

    private function bandung(): Destination
    {
        return new Destination(23001, 'COBLONG, BANDUNG, JAWA BARAT, 40132', 'JAWA BARAT', 'BANDUNG', 'COBLONG', 'DAGO', '40132');
    }

    public function test_a_new_store_can_set_where_it_ships_from(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('seller.store.store'), [
            'name' => 'Toko Bandung',
            'slug' => 'toko-bandung',
            'origin_token' => $this->bandung()->token(),
        ])->assertSessionHasNoErrors();

        $store = $user->store()->sole();
        $this->assertSame(23001, $store->origin_destination_id);
        $this->assertSame('BANDUNG', $store->origin_city);
        $this->assertTrue($store->hasShippingOrigin());
    }

    public function test_the_seller_page_warns_until_the_origin_is_set(): void
    {
        $store = Store::factory()->active()->create();
        $page = fn () => $this->actingAs($store->user)->get(route('seller.store.show'));

        $page()->assertInertia(fn (Assert $p) => $p->where('store.origin_label', null));

        $this->actingAs($store->user)->put(route('seller.store.update'), [
            'name' => $store->name,
            'origin_token' => $this->bandung()->token(),
        ])->assertSessionHasNoErrors();

        $page()->assertInertia(fn (Assert $p) => $p->where('store.origin_label', 'COBLONG, BANDUNG, JAWA BARAT, 40132'));
    }

    public function test_saving_without_a_new_origin_keeps_the_current_one(): void
    {
        $store = Store::factory()->active()->create();
        $store->setShippingOrigin($this->bandung());
        $store->save();

        $this->actingAs($store->user)->put(route('seller.store.update'), [
            'name' => 'Nama Baru',
            'origin_token' => '',
        ])->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame('Nama Baru', $store->name);
        $this->assertSame(23001, $store->origin_destination_id);
    }

    public function test_a_forged_origin_is_rejected(): void
    {
        $store = Store::factory()->active()->create();

        $this->actingAs($store->user)->put(route('seller.store.update'), [
            'name' => $store->name,
            'origin_token' => 'eyJpdiI6ImZha2UifQ==',
            // Raw columns from the form are ignored: not fillable.
            'origin_destination_id' => 1,
        ])->assertSessionHasErrors(['origin_token' => 'Pilih kecamatan asal pengiriman dari daftar saran.']);

        $this->assertNull($store->fresh()?->origin_destination_id);
    }
}
