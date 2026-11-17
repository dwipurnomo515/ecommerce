<?php

namespace Tests\Feature\Settings;

use App\Models\Address;
use App\Models\User;
use App\Shipping\Destination;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AddressBookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function senayan(): Destination
    {
        return new Destination(17473, 'SENAYAN, KEBAYORAN BARU, JAKARTA SELATAN, DKI JAKARTA, 12120',
            'DKI JAKARTA', 'JAKARTA SELATAN', 'KEBAYORAN BARU', 'SENAYAN', '12120');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'label' => 'Rumah',
            'recipient_name' => 'Budi',
            'phone' => '0812 3456 7890',
            'street' => 'Jl. Merdeka No. 10, RT 01/RW 02',
            'notes' => null,
            'destination_token' => $this->senayan()->token(),
            'is_primary' => false,
        ], $overrides);
    }

    private function primaryId(): ?int
    {
        return $this->user->addresses()->where('is_primary', true)->value('id');
    }

    public function test_the_first_address_becomes_primary_and_the_region_comes_from_the_token(): void
    {
        $this->actingAs($this->user)
            ->post(route('addresses.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('addresses.index'));

        $address = $this->user->addresses()->sole();
        $this->assertTrue($address->is_primary);
        $this->assertSame(17473, $address->destination_id);
        $this->assertSame('KEBAYORAN BARU', $address->district);
        $this->assertSame('081234567890', $address->phone, 'Phone normalised.');
    }

    public function test_a_tampered_destination_token_is_rejected(): void
    {
        $token = $this->senayan()->token();
        $tampered = substr($token, 0, -6).'AAAAAA';

        $this->actingAs($this->user)
            ->post(route('addresses.store'), $this->payload(['destination_token' => $tampered]))
            ->assertSessionHasErrors(['destination_token' => 'Pilih kecamatan/kelurahan dari daftar saran.']);

        $this->assertSame(0, Address::query()->count());
    }

    public function test_phone_numbers_are_normalised_or_rejected(): void
    {
        $this->actingAs($this->user)->post(route('addresses.store'), $this->payload(['phone' => '+62 812-3456-7890']));
        $this->assertSame('081234567890', $this->user->addresses()->value('phone'));

        foreach (['021 555 1234', '0812', 'nomor saya'] as $bad) {
            $this->actingAs($this->user)
                ->post(route('addresses.store'), $this->payload(['phone' => $bad]))
                ->assertSessionHasErrors('phone');
        }
    }

    public function test_there_is_always_exactly_one_primary_address(): void
    {
        $first = Address::factory()->for($this->user)->primary()->create();
        $second = Address::factory()->for($this->user)->create();

        // A new address marked primary takes over.
        $this->actingAs($this->user)->post(route('addresses.store'), $this->payload(['is_primary' => true]));
        $third = $this->user->addresses()->reorder()->latest('id')->first();
        $this->assertSame($third?->id, $this->primaryId());

        // "Jadikan utama" on another one.
        $this->actingAs($this->user)->put(route('addresses.primary', $second->id))->assertRedirect();
        $this->assertSame($second->id, $this->primaryId());

        // Deleting the primary promotes the newest remaining address.
        $this->actingAs($this->user)->delete(route('addresses.destroy', $second->id));
        $this->assertSame($third?->id, $this->primaryId());

        $this->assertSame(1, $this->user->addresses()->where('is_primary', true)->count());
        $this->assertTrue($first->fresh()?->exists);
    }

    public function test_the_database_refuses_a_second_primary(): void
    {
        Address::factory()->for($this->user)->primary()->create();

        $this->expectException(UniqueConstraintViolationException::class);
        Address::factory()->for($this->user)->primary()->create();
    }

    public function test_editing_without_a_new_token_keeps_the_region(): void
    {
        $address = Address::factory()->for($this->user)->primary()->create(['district' => 'KEBAYORAN BARU']);

        $this->actingAs($this->user)
            ->put(route('addresses.update', $address->id), $this->payload(['destination_token' => null, 'recipient_name' => 'Ani']))
            ->assertSessionHasNoErrors();

        $address->refresh();
        $this->assertSame('Ani', $address->recipient_name);
        $this->assertSame('KEBAYORAN BARU', $address->district);
    }

    public function test_at_most_ten_addresses(): void
    {
        Address::factory()->for($this->user)->count(Address::MAX_PER_USER)->create();

        $this->actingAs($this->user)
            ->post(route('addresses.store'), $this->payload())
            ->assertSessionHasErrors('recipient_name');

        $this->assertSame(Address::MAX_PER_USER, $this->user->addresses()->count());
    }

    public function test_nobody_can_touch_another_users_addresses(): void
    {
        $address = Address::factory()->primary()->create();

        $this->actingAs($this->user)->put(route('addresses.update', $address->id), $this->payload())->assertNotFound();
        $this->actingAs($this->user)->put(route('addresses.primary', $address->id))->assertNotFound();
        $this->actingAs($this->user)->delete(route('addresses.destroy', $address->id))->assertNotFound();

        $this->assertNotNull($address->fresh());
    }

    public function test_the_page_lists_the_primary_address_first(): void
    {
        Address::factory()->for($this->user)->create(['recipient_name' => 'Lain']);
        Address::factory()->for($this->user)->primary()->create(['recipient_name' => 'Utama']);

        $this->actingAs($this->user)
            ->get(route('addresses.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/addresses')
                ->where('addresses.0.recipient_name', 'Utama')
                ->where('addresses.0.is_primary', true));
    }
}
