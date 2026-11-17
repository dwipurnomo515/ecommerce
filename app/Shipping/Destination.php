<?php

namespace App\Shipping;

/**
 * A place parcels can be sent to (sub-district level). `id` is the
 * provider's id, needed later to price a shipment.
 */
final readonly class Destination
{
    public function __construct(
        public int $id,
        public string $label,
        public string $province,
        public string $city,
        public string $district,
        public string $subdistrict,
        public string $postalCode,
    ) {}

    /**
     * @return array{id: int, label: string, province: string, city: string, district: string, subdistrict: string, postal_code: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'province' => $this->province,
            'city' => $this->city,
            'district' => $this->district,
            'subdistrict' => $this->subdistrict,
            'postal_code' => $this->postalCode,
        ];
    }
}
