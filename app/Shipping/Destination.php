<?php

namespace App\Shipping;

use Illuminate\Contracts\Encryption\DecryptException;

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
     * An opaque, tamper-proof copy of this destination for the browser to
     * send back when an address is saved.
     *
     * The shipping API can't be asked "what is destination 17473?", so the
     * server can't re-check a destination that comes back from a form. If
     * the form sent id + names in plain fields, a buyer could pair any id
     * with any names. encrypt() is authenticated encryption (AES + MAC):
     * any change to the token makes decrypting it fail.
     */
    public function token(): string
    {
        return encrypt($this->toArray());
    }

    /** Null if the token was altered, made with another APP_KEY, or is junk. */
    public static function fromToken(string $token): ?self
    {
        try {
            /** @var array{id: int, label: string, province: string, city: string, district: string, subdistrict: string, postal_code: string} $data */
            $data = decrypt($token);

            return new self(
                id: $data['id'],
                label: $data['label'],
                province: $data['province'],
                city: $data['city'],
                district: $data['district'],
                subdistrict: $data['subdistrict'],
                postalCode: $data['postal_code'],
            );
        } catch (DecryptException) {
            return null;
        }
    }

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
