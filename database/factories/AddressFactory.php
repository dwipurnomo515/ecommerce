<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Rumah',
            'recipient_name' => 'Penerima Contoh',
            'phone' => '0812'.fake()->numerify('########'),
            'street' => 'Jl. Contoh No. '.fake()->numberBetween(1, 200).', RT 01/RW 02',
            'notes' => null,
            'destination_id' => 17473,
            'province' => 'DKI JAKARTA',
            'city' => 'JAKARTA SELATAN',
            'district' => 'KEBAYORAN BARU',
            'subdistrict' => 'SENAYAN',
            'postal_code' => '12120',
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
