<?php

namespace App\Shipping;

/**
 * A pretend shipping service for LOCAL development without a RajaOngkir
 * key: a few sample regions and a flat rate, all labelled "Contoh" so
 * nobody mistakes them for real data. Never bound in tests or production
 * (see AppServiceProvider).
 */
class FlatRateShipping implements ShippingProvider
{
    /** Sample regions (ids far from RajaOngkir's real ones). */
    private const REGIONS = [
        [900001, 'KEBAYORAN BARU', 'JAKARTA SELATAN', 'DKI JAKARTA', 'SENAYAN', '12190'],
        [900002, 'COBLONG', 'BANDUNG', 'JAWA BARAT', 'DAGO', '40135'],
        [900003, 'GUBENG', 'SURABAYA', 'JAWA TIMUR', 'AIRLANGGA', '60286'],
        [900004, 'DEPOK', 'SLEMAN', 'DI YOGYAKARTA', 'CATURTUNGGAL', '55281'],
    ];

    public function searchDestinations(string $query): array
    {
        $query = mb_strtolower(trim($query));
        $matches = [];

        foreach (self::REGIONS as [$id, $district, $city, $province, $subdistrict, $zip]) {
            $label = "{$subdistrict}, {$district}, {$city}, {$province}, {$zip} (Contoh)";

            if (str_contains(mb_strtolower($label), $query)) {
                $matches[] = new Destination($id, $label, $province, $city, $district, $subdistrict, $zip);
            }
        }

        return $matches;
    }

    public function quote(int $originId, int $destinationId, int $grams): array
    {
        $kilos = max(1, (int) ceil($grams / 1000));

        return [
            new ShippingOption('contoh', 'Kurir Contoh', 'REG', 'Reguler (Contoh)', 9_000 + 2_000 * $kilos, '2-3 day'),
            new ShippingOption('contoh', 'Kurir Contoh', 'KILAT', 'Kilat (Contoh)', 20_000 + 4_000 * $kilos, '1 day'),
        ];
    }

    public static function sample(int $index): Destination
    {
        [$id, $district, $city, $province, $subdistrict, $zip] = self::REGIONS[$index];

        return new Destination($id, "{$subdistrict}, {$district}, {$city}, {$province}, {$zip} (Contoh)", $province, $city, $district, $subdistrict, $zip);
    }
}
