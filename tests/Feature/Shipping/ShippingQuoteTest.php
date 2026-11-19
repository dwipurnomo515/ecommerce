<?php

namespace Tests\Feature\Shipping;

use App\Shipping\ShippingOption;
use App\Shipping\ShippingProvider;
use App\Shipping\ShippingUnavailable;
use App\Shipping\UnknownRegion;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingQuoteTest extends TestCase
{
    private const URL = 'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rajaongkir.key' => 'test-key',
            'services.rajaongkir.couriers' => 'jne:sicepat',
        ]);
    }

    private function shipping(): ShippingProvider
    {
        return app(ShippingProvider::class);
    }

    /**
     * Shape from the Komerce docs (calculate-domestic-cost).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function komerce(array $rows): array
    {
        return [
            'meta' => ['message' => 'Success Calculate Domestic Shipping cost', 'code' => 200, 'status' => 'success'],
            'data' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $code, string $service, int $cost, string $etd = '2-3 day'): array
    {
        return ['name' => strtoupper($code), 'code' => $code, 'service' => $service, 'description' => 'Layanan '.$service, 'cost' => $cost, 'etd' => $etd];
    }

    public function test_options_are_mapped_and_sorted_cheapest_first(): void
    {
        Http::fake([self::URL => Http::response($this->komerce([
            $this->row('jne', 'YES', 35_000, '1 day'),
            $this->row('sicepat', 'REG', 16_000),
            $this->row('jne', 'REG', 18_000),
        ]))]);

        $options = $this->shipping()->quote(17473, 31555, 1200);

        $this->assertSame(['sicepat:REG', 'jne:REG', 'jne:YES'], array_map(fn (ShippingOption $o) => $o->key(), $options));
        $this->assertSame(16_000, $options[0]->cost);
        $this->assertSame('1 day', $options[2]->etd);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->hasHeader('key', 'test-key')
            && $r->isForm()
            && $r['origin'] === 17473
            && $r['destination'] === 31555
            && $r['weight'] === 1200
            && $r['courier'] === 'jne:sicepat');
    }

    public function test_the_same_route_and_weight_is_quoted_from_cache(): void
    {
        Http::fake([self::URL => Http::response($this->komerce([$this->row('jne', 'REG', 18_000)]))]);

        $this->shipping()->quote(1, 2, 1000);
        $this->shipping()->quote(1, 2, 1000);
        $this->shipping()->quote(1, 2, 2000); // different weight: new call

        Http::assertSentCount(2);
    }

    public function test_free_or_broken_rows_are_dropped(): void
    {
        Http::fake([self::URL => Http::response($this->komerce([
            $this->row('jne', 'REG', 0),
            ['code' => 'sicepat', 'cost' => 9_000], // no service
            $this->row('sicepat', 'REG', 16_000),
        ]))]);

        $options = $this->shipping()->quote(1, 2, 1000);

        $this->assertCount(1, $options);
        $this->assertSame('sicepat:REG', $options[0]->key());
    }

    public function test_no_courier_on_the_route_is_an_empty_list(): void
    {
        Http::fake([self::URL => Http::response(['meta' => ['message' => 'cost not found', 'code' => 400, 'status' => 'error'], 'data' => null], 400)]);

        $this->assertSame([], $this->shipping()->quote(1, 2, 1000));
    }

    public function test_an_unknown_region_names_the_side_that_is_wrong(): void
    {
        // Bodies as returned by the live API for a region ID it does not know.
        Http::fake([self::URL => Http::sequence()
            ->push(['meta' => ['message' => 'origin not found', 'code' => 404, 'status' => 'error'], 'data' => null], 404)
            ->push(['meta' => ['message' => 'destination not found', 'code' => 404, 'status' => 'error'], 'data' => null], 404)
            ->push(['meta' => ['message' => 'Not Found'], 'data' => null], 404)]);

        foreach ([UnknownRegion::ORIGIN, UnknownRegion::DESTINATION] as $expected) {
            try {
                $this->shipping()->quote(900002, 31555, 1000);
                $this->fail('Expected UnknownRegion.');
            } catch (UnknownRegion $e) {
                $this->assertSame($expected, $e->side);
            }
        }

        // A 404 that does not name a side (e.g. a wrong URL) stays an outage.
        try {
            $this->shipping()->quote(900002, 31555, 1000);
            $this->fail('Expected ShippingUnavailable.');
        } catch (ShippingUnavailable $e) {
            $this->assertNotInstanceOf(UnknownRegion::class, $e);
        }
    }

    public function test_an_outage_throws_and_is_not_cached(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(['meta' => ['message' => 'error']], 500)
            ->push($this->komerce([$this->row('jne', 'REG', 18_000)]))]);

        try {
            $this->shipping()->quote(1, 2, 1000);
            $this->fail('Expected ShippingUnavailable.');
        } catch (ShippingUnavailable) {
            // expected
        }

        $this->assertCount(1, $this->shipping()->quote(1, 2, 1000));
    }
}
