<?php

namespace App\Shipping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Komerce RajaOngkir, "direct search" method:
 * https://rajaongkir.com/docs/shipping-cost/endpoint-rajaongkir-for-search-base/search-destination-rajaongkir
 */
class RajaOngkir implements ShippingProvider
{
    public function __construct(
        private readonly ?string $key,
        private readonly string $baseUrl,
    ) {}

    public function searchDestinations(string $query): array
    {
        $query = mb_strtolower(trim($query));

        // Regions practically never change, and every lookup costs API
        // quota: the same search is answered from cache for a day.
        // (Only successful answers are cached; failures throw.)
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            'rajaongkir:destinations:'.md5($query),
            now()->addDay(),
            fn () => $this->fetchDestinations($query),
        );

        return array_map(fn (array $row) => new Destination(
            id: (int) $row['id'],
            label: (string) $row['label'],
            province: (string) ($row['province_name'] ?? ''),
            city: (string) ($row['city_name'] ?? ''),
            district: (string) ($row['district_name'] ?? ''),
            subdistrict: (string) ($row['subdistrict_name'] ?? ''),
            postalCode: (string) ($row['zip_code'] ?? ''),
        ), $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchDestinations(string $query): array
    {
        try {
            $response = $this->http()->get('destination/domestic-destination', [
                'search' => $query,
                'limit' => 10,
                'offset' => 0,
            ]);
        } catch (ConnectionException $e) {
            throw new ShippingUnavailable('RajaOngkir unreachable: '.$e->getMessage(), previous: $e);
        }

        // "Nothing matches" comes back as 404, which for us is just [].
        if ($response->status() === 404) {
            return [];
        }

        if ($response->failed()) {
            throw new ShippingUnavailable("RajaOngkir search failed: HTTP {$response->status()}");
        }

        /** @var list<array<string, mixed>> */
        return $response->json('data') ?? [];
    }

    private function http(): PendingRequest
    {
        if ($this->key === null || $this->key === '') {
            throw new ShippingUnavailable('RAJAONGKIR_API_KEY is not set.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['key' => $this->key])
            ->acceptJson()
            // Never let a slow API hang the buyer's request (and a PHP
            // worker) for the default 30 s.
            ->connectTimeout(3)
            ->timeout(8);
    }
}
