<?php

namespace Tests\Feature;

use App\Shipping\FlatRateShipping;
use App\Shipping\RajaOngkir;
use App\Shipping\ShippingProvider;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_are_never_created_outside_local(): void
    {
        $this->expectException(RuntimeException::class);

        $this->seed(DemoSeeder::class);
    }

    public function test_outside_local_the_real_shipping_client_is_used_even_without_a_key(): void
    {
        config(['services.rajaongkir.key' => null]);
        $this->app->forgetInstance(ShippingProvider::class);

        $this->assertInstanceOf(RajaOngkir::class, app(ShippingProvider::class));
    }

    public function test_the_sample_courier_is_labelled_and_charges_by_weight(): void
    {
        $shipping = new FlatRateShipping;

        $this->assertStringContainsString('(Contoh)', $shipping->searchDestinations('bandung')[0]->label);
        $this->assertSame(13_000, $shipping->quote(1, 2, 1500)[0]->cost); // 9.000 + 2 kg × 2.000
        $this->assertSame([], $shipping->searchDestinations('tidak ada'));
    }
}
