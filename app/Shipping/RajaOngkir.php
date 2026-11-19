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
        /** Colon separated, e.g. "jne:sicepat:jnt". */
        private readonly string $couriers = 'jne:sicepat:jnt:pos',
    ) {}

    public function quote(int $originId, int $destinationId, int $grams): array
    {
        $grams = max(1, $grams);

        // Prices change rarely within an hour, and the same cart is quoted
        // again on every checkout page load: cache, and save quota.
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            "rajaongkir:cost:{$originId}:{$destinationId}:{$grams}:{$this->couriers}",
            now()->addHour(),
            fn () => $this->fetchCosts($originId, $destinationId, $grams),
        );

        $options = array_map(fn (array $row) => new ShippingOption(
            courierCode: (string) $row['code'],
            courierName: (string) ($row['name'] ?? $row['code']),
            service: (string) $row['service'],
            description: (string) ($row['description'] ?? ''),
            cost: (int) $row['cost'],
            etd: (string) ($row['etd'] ?? ''),
        ), $rows);

        usort($options, fn (ShippingOption $a, ShippingOption $b) => $a->cost <=> $b->cost);

        return $options;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchCosts(int $originId, int $destinationId, int $grams): array
    {
        try {
            $response = $this->http()->asForm()->post('calculate/domestic-cost', [
                'origin' => $originId,
                'destination' => $destinationId,
                'weight' => $grams,
                'courier' => $this->couriers,
            ]);
        } catch (ConnectionException $e) {
            throw new ShippingUnavailable('RajaOngkir unreachable: '.$e->getMessage(), previous: $e);
        }

        // The docs list 400 for "cost not found": no courier on this route.
        // That is an answer (no options), not an outage.
        if ($response->status() === 400) {
            return [];
        }

        // 404 with "origin not found" / "destination not found": the region
        // ID is not theirs (checked against the live API). Name the side, so
        // the right person is asked to fix it.
        if ($response->status() === 404) {
            $message = strtolower((string) $response->json('meta.message'));

            foreach ([UnknownRegion::ORIGIN, UnknownRegion::DESTINATION] as $side) {
                if (str_contains($message, $side)) {
                    throw new UnknownRegion($side, "RajaOngkir: {$side} {$originId}→{$destinationId} not found");
                }
            }
        }

        if ($response->failed()) {
            throw new ShippingUnavailable("RajaOngkir cost failed: HTTP {$response->status()}");
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json('data') ?? [];

        // A zero or missing price would let an order through with free
        // shipping by accident. Drop such rows.
        return array_values(array_filter($rows, fn (array $r) => isset($r['code'], $r['service']) && (int) ($r['cost'] ?? 0) > 0));
    }

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
