<?php

namespace Tests\Feature\Shop;

use App\Models\User;
use App\Shipping\ShippingProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DestinationSearchTest extends TestCase
{
    private const URL = 'https://rajaongkir.komerce.id/api/v1/destination/domestic-destination*';

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.rajaongkir.key' => 'test-key']);
        $this->buyer = User::factory()->make(['id' => 1]);
    }

    /**
     * Shape copied from the Komerce docs (search-destination-rajaongkir).
     *
     * @return array<string, mixed>
     */
    private function komerceResponse(): array
    {
        return [
            'meta' => ['message' => 'Success Get Domestic Destinations', 'code' => 200, 'status' => 'success'],
            'data' => [[
                'id' => 17473,
                'label' => 'KEBAYORAN BARU, JAKARTA SELATAN, DKI JAKARTA, 12120',
                'province_name' => 'DKI JAKARTA',
                'city_name' => 'JAKARTA SELATAN',
                'district_name' => 'KEBAYORAN BARU',
                'subdistrict_name' => 'SENAYAN',
                'zip_code' => '12120',
            ]],
        ];
    }

    private function search(string $q): TestResponse
    {
        return $this->actingAs($this->buyer)->getJson(route('destinations.search', ['q' => $q]));
    }

    public function test_results_are_mapped_and_the_api_key_is_sent(): void
    {
        Http::fake([self::URL => Http::response($this->komerceResponse())]);

        $this->search('Kebayoran')
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => 17473,
                'label' => 'KEBAYORAN BARU, JAKARTA SELATAN, DKI JAKARTA, 12120',
                'province' => 'DKI JAKARTA',
                'city' => 'JAKARTA SELATAN',
                'district' => 'KEBAYORAN BARU',
                'subdistrict' => 'SENAYAN',
                'postal_code' => '12120',
            ]]]);

        Http::assertSent(fn (Request $r) => $r->hasHeader('key', 'test-key')
            && $r['search'] === 'kebayoran');
    }

    public function test_the_same_search_is_answered_from_cache(): void
    {
        Http::fake([self::URL => Http::response($this->komerceResponse())]);

        $this->search('kebayoran')->assertOk();
        $this->search('  KEBAYORAN ')->assertOk(); // normalised: same cache key

        Http::assertSentCount(1);
    }

    public function test_no_match_is_an_empty_list_not_an_error(): void
    {
        Http::fake([self::URL => Http::response([
            'meta' => ['message' => 'Domestic Destinations Data not found', 'code' => 404, 'status' => 'error'],
            'data' => null,
        ], 404)]);

        $this->search('zzzzz')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_an_api_failure_is_a_friendly_503_and_is_not_cached(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(['meta' => ['message' => 'Server error']], 500)
            ->push($this->komerceResponse())]);

        $this->search('kebayoran')
            ->assertStatus(503)
            ->assertJson(['message' => 'Pencarian wilayah sedang tidak tersedia. Coba lagi sebentar lagi.']);

        // The failure must not be remembered for a day.
        $this->search('kebayoran')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_missing_api_key_is_a_503_without_calling_out(): void
    {
        config(['services.rajaongkir.key' => null]);
        $this->app->forgetInstance(ShippingProvider::class);
        Http::fake();

        $this->search('kebayoran')->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_guests_cannot_spend_our_quota(): void
    {
        Http::fake();

        $this->getJson(route('destinations.search', ['q' => 'kebayoran']))->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_queries_must_be_3_to_50_characters(): void
    {
        Http::fake();

        $this->search('ab')->assertUnprocessable();
        $this->search(str_repeat('a', 51))->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_searching_is_rate_limited(): void
    {
        Http::fake([self::URL => Http::response($this->komerceResponse())]);

        foreach (range(1, 30) as $i) {
            $this->search("kota {$i}")->assertOk();
        }

        $this->search('kota 31')->assertTooManyRequests();
    }
}
