<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $label
 * @property string $recipient_name
 * @property string $phone
 * @property string $street
 * @property string|null $notes
 * @property int $destination_id
 * @property string $province
 * @property string $city
 * @property string $district
 * @property string $subdistrict
 * @property string $postal_code
 * @property bool $is_primary
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
// Not fillable: user_id (rows are made through $user->addresses()) and
// is_primary (only SaveAddress / makePrimary change it, keeping "one
// primary" true).
#[Fillable([
    'label', 'recipient_name', 'phone', 'street', 'notes',
    'destination_id', 'province', 'city', 'district', 'subdistrict', 'postal_code',
])]
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    public const MAX_PER_USER = 10;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_primary' => false,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Senayan, Kebayoran Baru, Jakarta Selatan, DKI Jakarta 12120" */
    public function regionLabel(): string
    {
        return implode(', ', array_filter([$this->subdistrict, $this->district, $this->city, $this->province]))
            .' '.$this->postal_code;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'destination_id' => 'integer',
            'is_primary' => 'boolean',
        ];
    }
}
