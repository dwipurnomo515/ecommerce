<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use App\Shipping\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Product $product;

    private Order $order;

    private OrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.rajaongkir.key' => 'test-key']);
        Http::fake(['*/calculate/domestic-cost' => Http::response(['data' => [
            ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => '', 'cost' => 18_000, 'etd' => ''],
        ]])]);

        $this->buyer = User::factory()->create(['name' => 'Budi Santoso']);
        Address::factory()->for($this->buyer)->primary()->create();
        $store = Store::factory()->active()->create(['slug' => 'toko-contoh']);
        $store->setShippingOrigin(new Destination(1, 'ASAL', 'P', 'K', 'D', 'S', '10000'));
        $store->save();

        $this->product = Product::factory()->active()->for($store)->create(['slug' => 'kaos']);
        $variant = ProductVariant::factory()->for($this->product)->create(['price' => 50_000, 'stock' => 5]);
        $this->buyer->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'address_id' => $this->buyer->addresses()->value('id'),
            'shipping' => [$store->id => 'jne:REG'],
            'idempotency_key' => (string) Str::uuid(),
            'expected_total' => 68_000,
        ])->assertSessionHasNoErrors();

        $this->order = Order::query()->sole();
        $this->item = $this->order->items()->sole();
    }

    private function complete(): void
    {
        $this->order->forceFill(['status' => OrderStatus::Completed, 'completed_at' => now()])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function review(array $data = ['rating' => 5, 'body' => 'Bahannya adem.'], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->buyer)->post(route('reviews.store', $this->item), $data);
    }

    public function test_a_buyer_reviews_a_completed_purchase_and_the_totals_update(): void
    {
        $this->complete();

        $this->review()->assertSessionHasNoErrors();
        $this->assertSame([1, 5], [$this->product->fresh()?->rating_count, $this->product->fresh()?->rating_sum]);

        $review = Review::query()->sole();
        $this->assertSame($this->product->id, $review->product_id);
        $this->assertSame($this->order->store_id, $review->store_id);

        $store = $this->product->store->fresh();
        $this->assertSame([1, 5], [$store?->rating_count, $store?->rating_sum], 'Store reputation moves too.');
        $this->assertSame(['average' => 5.0, 'count' => 1], $store?->ratingSummary());
    }

    public function test_only_completed_orders_can_be_reviewed(): void
    {
        foreach ([OrderStatus::Paid, OrderStatus::Shipped] as $status) {
            $this->order->status = $status;
            $this->order->save();

            $this->review()->assertSessionHasErrors(['review' => 'Ulasan bisa ditulis setelah pesanan selesai.']);
        }

        $this->assertSame(0, Review::query()->count());
    }

    public function test_one_review_per_purchase(): void
    {
        $this->complete();

        $this->review(['rating' => 5]);
        $this->review(['rating' => 1])->assertSessionHasErrors(['review' => 'Anda sudah mengulas barang ini.']);

        $this->assertSame([1, 5], [$this->product->fresh()?->rating_count, $this->product->fresh()?->rating_sum]);
    }

    public function test_reviews_close_after_sixty_days(): void
    {
        $this->complete();
        $this->travel(Review::WINDOW_DAYS + 1)->days();

        $this->review()->assertSessionHasErrors(['review' => 'Batas waktu menulis ulasan sudah lewat.']);
    }

    public function test_nobody_reviews_someone_elses_purchase(): void
    {
        $this->complete();

        $this->review(as: User::factory()->create())->assertNotFound();
        $this->assertSame(0, Review::query()->count());
    }

    public function test_rating_must_be_one_to_five(): void
    {
        $this->complete();

        foreach ([0, 6, 'lima', null] as $bad) {
            $this->review(['rating' => $bad])->assertSessionHasErrors('rating');
        }
    }

    public function test_the_product_page_shows_the_average_and_a_short_name(): void
    {
        $this->complete();
        $this->review(['rating' => 4, 'body' => 'Bagus.']);
        $this->product->forceFill(['rating_count' => 3, 'rating_sum' => 13])->save(); // pretend 2 more

        $this->get(route('shop.products.show', ['toko-contoh', 'kaos']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rating.average', 4.3)
                ->where('rating.count', 3)
                ->where('reviews.0.author', 'Budi S.')
                ->where('reviews.0.rating', 4)
                ->missing('reviews.0.user_id'));
    }

    public function test_products_without_reviews_show_no_rating_at_all(): void
    {
        $this->get(route('shop.products.show', ['toko-contoh', 'kaos']))
            ->assertInertia(fn (Assert $page) => $page->where('rating', null)->has('reviews', 0));

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('products.0.rating', null));
    }

    public function test_the_order_page_offers_a_review_only_when_allowed(): void
    {
        $page = fn () => $this->actingAs($this->buyer)->get(route('checkout.placed', $this->order->checkout->code));

        $page()->assertInertia(fn (Assert $p) => $p->where('checkout.orders.0.items.0.can_review', false));

        $this->complete();
        $page()->assertInertia(fn (Assert $p) => $p->where('checkout.orders.0.items.0.can_review', true));

        $this->review(['rating' => 3]);
        $page()->assertInertia(fn (Assert $p) => $p
            ->where('checkout.orders.0.items.0.can_review', false)
            ->where('checkout.orders.0.items.0.review_rating', 3));
    }

    public function test_public_names_never_show_a_full_surname(): void
    {
        $this->assertSame('Budi S.', Review::publicName('Budi Santoso'));
        $this->assertSame('Ani', Review::publicName('  Ani '));
        $this->assertSame('Siti N.', Review::publicName('Siti nurhaliza binti Ahmad'));
    }
}
