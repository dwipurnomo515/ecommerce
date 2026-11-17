<?php

namespace App\Shipping;

/**
 * One way to send a parcel, e.g. JNE REG, Rp 18.000, 2-3 hari.
 */
final readonly class ShippingOption
{
    public function __construct(
        public string $courierCode,
        public string $courierName,
        public string $service,
        public string $description,
        /** Whole Rupiah. */
        public int $cost,
        /** As the courier states it, e.g. "2-3 day". May be empty. */
        public string $etd,
    ) {}

    /**
     * "jne:REG". At checkout the browser sends only this key; the price is
     * looked up again on the server, never taken from the browser.
     */
    public function key(): string
    {
        return $this->courierCode.':'.$this->service;
    }

    /**
     * @return array{key: string, courier: string, courier_name: string, service: string, description: string, cost: int, etd: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'courier' => $this->courierCode,
            'courier_name' => $this->courierName,
            'service' => $this->service,
            'description' => $this->description,
            'cost' => $this->cost,
            'etd' => $this->etd,
        ];
    }
}
