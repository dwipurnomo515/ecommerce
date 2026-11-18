<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Ledger\Ledger;
use App\Models\Address;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Shipping\Destination;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class StoreLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Store $store;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.rajaongkir.key' => 'test-key', 'marketplace.commission_bps' => 500]);
        Http::fake(['*/calculate/domestic-cost' => Http::response(['data' => [
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => '', 'cost' => 18_000, 'etd' => ''],
        ]])]);

        $this->buyer = User::factory()->create();
        Address::factory()->for($this->buyer)->primary()->create();
        $this->store = Store::factory()->active()->create();
        $this->store->setShippingOrigin(new Destination(1, 'ASAL', 'P', 'K', 'D', 'S', '10000'));
        $this->store->save();

        // 99.999: 5 % is 4.999,95, which must round DOWN to 4.999.
        $variant = ProductVariant::factory()
            ->for(Product::factory()->active()->for($this->store))
            ->create(['price' => 99_999, 'stock' => 5]);
        $this->buyer->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'address_id' => $this->buyer->addresses()->value('id'),
            'shipping' => [$this->store->id => 'jne:REG'],
            'idempotency_key' => (string) Str::uuid(),
            'expected_total' => 117_999,
        ])->assertSessionHasNoErrors();

        $this->order = Order::query()->sole();
        $this->order->forceFill(['status' => OrderStatus::Shipped, 'shipped_at' => now()])->save();
    }

    private function balance(): int
    {
        return (int) $this->store->fresh()?->balance;
    }

    public function test_completing_an_order_pays_the_store_minus_commission(): void
    {
        $this->assertSame(0, $this->balance(), 'Nothing while the parcel is on its way (escrow).');

        $this->actingAs($this->buyer)->post(route('orders.confirm', $this->order))->assertSessionHasNoErrors();

        // 99.999 + 18.000 ongkir − 4.999 komisi (5 % of items only, rounded down)
        $this->assertSame(113_000, $this->balance());

        $entries = LedgerEntry::query()->orderBy('id')->get();
        $this->assertSame([117_999, -4_999], $entries->pluck('amount')->all());
        $this->assertSame([117_999, 113_000], $entries->pluck('balance_after')->all());
        $this->assertSame('Komisi Zetago 5% pesanan '.$this->order->number, $entries[1]->description);
    }

    public function test_the_rate_is_frozen_when_the_order_is_placed(): void
    {
        config(['marketplace.commission_bps' => 1_000]); // raised to 10 % later

        $this->actingAs($this->buyer)->post(route('orders.confirm', $this->order));

        $this->assertSame(500, $this->order->fresh()?->commission_bps);
        $this->assertSame(113_000, $this->balance());
    }

    public function test_an_order_can_never_be_credited_twice(): void
    {
        $this->actingAs($this->buyer)->post(route('orders.confirm', $this->order));

        try {
            app(Ledger::class)->creditCompletedOrder($this->order->fresh() ?? $this->order);
            $this->fail('A second credit must be refused.');
        } catch (UniqueConstraintViolationException) {
            // expected: unique (order_id, type)
        }

        $this->assertSame(113_000, $this->balance());
        $this->assertSame(2, LedgerEntry::query()->count());
    }

    public function test_the_auto_complete_job_pays_the_store_too(): void
    {
        $this->travel(Order::AUTO_COMPLETE_DAYS + 1)->days();

        $this->artisan('orders:auto-complete');

        $this->assertSame(113_000, $this->balance());
    }

    public function test_the_balance_can_never_go_negative(): void
    {
        $this->expectException(QueryException::class); // CHECK (balance >= 0)

        app(Ledger::class)->post($this->store->id, null, 'adjustment', -1, 'Uji saldo minus');
    }

    public function test_ledger_entries_cannot_be_changed_or_deleted(): void
    {
        $entry = app(Ledger::class)->post($this->store->id, null, 'adjustment', 1_000, 'Uji');

        $this->expectException(LogicException::class);
        $entry->amount = 9_999_999;
        $entry->save();
    }

    public function test_the_balance_page_shows_available_held_and_history(): void
    {
        // fresh(): actingAs() reuses the SAME User object for every request
        // in a test, and $user->store, once loaded, stays cached on it
        // (with the old balance). Real requests load the user anew.
        $page = fn () => $this->actingAs($this->store->user->fresh() ?? $this->store->user)->get(route('seller.balance.index'));

        $page()->assertInertia(fn (Assert $p) => $p
            ->component('seller/balance/index')
            ->where('balance', 0)
            ->where('held', 113_000)
            ->where('commissionPercent', 5)
            ->has('entries.data', 0));

        $this->actingAs($this->buyer)->post(route('orders.confirm', $this->order));

        $page()->assertInertia(fn (Assert $p) => $p
            ->where('balance', 113_000)
            ->where('held', 0)
            ->where('entries.data.0.amount', -4_999)); // newest first
    }
}
